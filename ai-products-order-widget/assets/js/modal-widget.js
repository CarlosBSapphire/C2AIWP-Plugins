/**
 * AI Products Order Widget - Modal System
 * Version: 2.0.0
 * Platform-agnostic JavaScript for modal-based order flow
 */

(function () {
    'use strict';

    /**
     * Main Widget Controller
     */
    class AIProductsWidget {
        constructor(config = {}) {
            this.config = {
                apiProxy: config.apiProxy || '/wp-admin/admin-ajax.php',
                nonce: config.nonce || '',
                stripePublicKey: config.stripePublicKey || 'pk_test_51RO1TFI6Mo3ACLGTuEJTA0vmAS6XovFb3ym9oTp9kPW6OO7s9IZI9DTsxQfLaAdzLQqBB4bzQeFfDu6Ux4YpB2hw002QJW8iRr',
                ...config
            };

            this.state = {
                currentStep: 1,
                selectedProducts: [],
                selectedAddons: [],
                setupType: null,
                numberCount: 1,
                assignmentType: null,
                agentStyle: null,
                paymentInfo: {},
                termsAccepted: false
            };

            this.steps = [
                { id: 1, name: 'Products', handler: this.renderProductSelection.bind(this) },
                { id: 2, name: 'Payment', handler: this.renderPaymentForm.bind(this) },
                { id: 3, name: 'Setup', handler: this.renderCallSetup.bind(this) },
                { id: 4, name: 'Configuration', handler: this.renderConfiguration.bind(this) },
                { id: 5, name: 'Agent Style', handler: this.renderAgentStyle.bind(this) }
            ];

            // Pricing - will be loaded from n8n API
            this.pricing = {
                setup: 0,
                weekly: 0,
                products: {},  // Product-specific pricing
                addons: {},     // Addon-specific pricing
                setupFees: {}, // Setup fees by service count (1, 2, 3+)
                agentStyles: {} // Agent style pricing by type (Quick, Advanced, Conversational)
            };

            // Stripe elements
            this.stripe = null;
            this.cardElement = null;

            this.init();
        }

        async init() {
            this.createModal();
            this.attachEventListeners();

            // Load pricing data from n8n
            await this.loadPricing();
        }

        /**
         * Create modal structure
         */
        createModal() {
            const modalHTML = `
                <div class="aipw-modal-overlay" id="aipwModal">
                    <div class="aipw-modal">
                        <div class="aipw-progress">
                            <div class="aipw-progress-bar">
                                <div class="aipw-progress-fill" id="aipwProgressFill"></div>
                            </div>
                            <div class="aipw-progress-steps" id="aipwProgressSteps"></div>
                        </div>

                        <div class="aipw-modal-header" id="aipwModalHeader"></div>
                        <div class="aipw-modal-body" id="aipwModalBody"></div>
                        <div class="aipw-modal-footer" id="aipwModalFooter"></div>
                    </div>

                    <div class="aipw-summary" id="aipwSummary">
                        <div class="aipw-summary-title">Summary</div>
                        <div id="aipwSummaryContent"></div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
            this.modal = document.getElementById('aipwModal');
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            // Modal trigger button
            document.querySelectorAll('[data-aipw-open]').forEach(btn => {
                btn.addEventListener('click', () => this.openModal());
            });
        }

        /**
         * Open modal
         */
        openModal() {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.renderStep(1);
        }

        /**
         * Close modal
         */
        closeModal() {
            this.modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        /**
         * Load pricing data from n8n API
         */
        async loadPricing() {
            try {
                console.log('[loadPricing] Fetching pricing from n8n...');
                const response = await this.apiCall('get_pricing', {});

                // Handle the response structure: response.data is an array with cost_json inside
                const costData = response.success && response.data && response.data[0] && response.data[0].cost_json;

                if (costData) {
                    console.log('[loadPricing] Pricing data received:', response.data[0]);

                    // Parse rates - they come as numbers or dollar strings like "$99.00" or "$0.45"
                    // Convert to cents (integer) for calculations
                    const parseRate = (rateStr) => {
                        if (!rateStr) return 0;
                        if(typeof rateStr === 'number') return Math.round(rateStr * 100);
                        const cleaned = String(rateStr).replace(/[$,]/g, '');
                        const dollars = parseFloat(cleaned) || 0;
                        return Math.round(dollars * 100); // Convert to cents
                    };

                    // Process each pricing item
                    costData.forEach(item => {
                        const type = item.type;
                        const name = item.name;
                        const frequency = item.frequency;

                        // Handle One Time Charges (setup fees based on service count)
                        if (name === 'One Time Charge' && frequency === 'One Time') {
                            const cost = parseRate(item.cost);

                            if (type === '1 Service') {
                                this.pricing.setupFees['1'] = cost;
                            } else if (type === '2 Services') {
                                this.pricing.setupFees['2'] = cost;
                            } else if (type === '3+ Services') {
                                this.pricing.setupFees['3+'] = cost;
                            }

                            console.log(`[loadPricing] Setup fee for ${type}: ${cost} cents`);
                        }

                        // Handle Inbound/Outbound Calls (agent style pricing)
                        else if ((name === 'Inbound Calls' || name === 'Outbound Calls') && frequency === 'Weekly') {
                            const phone_per_minute = parseRate(item.phone_per_minute);
                            const phone_per_minute_overage = parseRate(item.phone_per_minute_overage);
                            const call_threshold = item.call_threshold ? parseInt(item.call_threshold) : 0;

                            // Store by agent style type (Quick, Advanced, Conversational)
                            const styleKey = type; // "Quick", "Advanced", or "Conversational"

                            if (!this.pricing.agentStyles[styleKey]) {
                                this.pricing.agentStyles[styleKey] = {
                                    phone_per_minute: 0,
                                    phone_per_minute_overage: 0,
                                    call_threshold: 0
                                };
                            }

                            // Combine Inbound and Outbound (they should have same rates)
                            this.pricing.agentStyles[styleKey].phone_per_minute = phone_per_minute;
                            this.pricing.agentStyles[styleKey].phone_per_minute_overage = phone_per_minute_overage;
                            this.pricing.agentStyles[styleKey].call_threshold = call_threshold;

                            console.log(`[loadPricing] ${styleKey} calls: ${phone_per_minute} cents/min`);
                        }

                        // Handle Email Agents
                        else if (name === 'Email Agents' && frequency === 'Weekly') {
                            const cost = parseRate(item.cost);
                            const email_threshold = item.email_threshold ? parseRate(item.email_threshold) : 0;
                            const email_cost_overage = parseRate(item.email_cost_overage);

                            this.pricing.products['emails'] = {
                                weekly: cost,
                                email_threshold: email_threshold,
                                email_cost_overage: email_cost_overage,
                                type: type, // "Basic"
                                frequency: frequency
                            };

                            console.log(`[loadPricing] Email Agents (${type}): ${cost} cents/week`);
                        }

                        // Handle Chat Agents
                        else if (name === 'Chat Agents' && frequency === 'Weekly') {
                            const cost = parseRate(item.cost);
                            const chat_threshold = parseRate(item.chat_threshold);
                            const chat_cost_overage = parseRate(item.chat_cost_overage);

                            this.pricing.products['chatbot'] = {
                                weekly: cost,
                                chat_threshold: chat_threshold,
                                chat_cost_overage: chat_cost_overage,
                                type: type, // "Basic"
                                frequency: frequency
                            };

                            console.log(`[loadPricing] Chat Agents (${type}): ${cost} cents/week`);
                        }

                        // Handle Addons
                        else if (type === 'Addons') {
                            const cost = parseRate(item.cost);

                            // Map addon names to frontend keys
                            const addonMapping = {
                                'Transcription & Call Recordings': 'Transcriptions & Recordings',
                                'QA': 'Quality Assurance'
                            };

                            const addonKey = addonMapping[name] || name;

                            this.pricing.addons[addonKey] = {
                                weekly: cost,
                                frequency: frequency,
                                type: type
                            };

                            console.log(`[loadPricing] Addon ${addonKey}: ${cost} cents/week`);
                        }

                        // Handle QA (Quality Assurance) - separate from Addons type
                        else if (name === 'QA') {
                            const cost = parseRate(item.cost);
                            const cost_per_lead = parseRate(item.cost_per_lead);

                            // Store QA pricing by type (Basic, Advanced)
                            const qaKey = `Quality Assurance (${type})`;

                            this.pricing.addons[qaKey] = {
                                weekly: cost,
                                cost_per_lead: cost_per_lead,
                                frequency: frequency,
                                type: type
                            };

                            console.log(`[loadPricing] ${qaKey}: ${cost} cents/week, ${cost_per_lead} cents/lead`);
                        }
                    });

                    console.log('[loadPricing] Parsed pricing:', {
                        setupFees: this.pricing.setupFees,
                        agentStyles: this.pricing.agentStyles,
                        products: this.pricing.products,
                        addons: this.pricing.addons
                    });

                    // Calculate initial totals based on default selections
                    this.calculatePricing();
                } else {
                    console.error('[loadPricing] Failed to load pricing:', response);
                    // Use fallback pricing
                    this.useFallbackPricing();
                }
            } catch (error) {
                console.error('[loadPricing] Error loading pricing:', error);
                // Use fallback pricing
                this.useFallbackPricing();
            }
        }

        /**
         * Use fallback pricing if API fails
         */
        useFallbackPricing() {
            console.warn('[useFallbackPricing] Using fallback pricing values');
            this.pricing.setup = 99999;
            this.pricing.weekly = 15000;
        }

        /**
         * Calculate pricing based on selected products, addons, and agent style
         */
        calculatePricing() {
            let setupTotal = 0;
            let weeklyTotal = 0;

            console.log('[calculatePricing] Starting calculation with:', {
                selectedProducts: this.state.selectedProducts,
                selectedAddons: this.state.selectedAddons,
                agentStyle: this.state.agentStyle
            });

            // Step 1: Calculate setup fee based on number of selected services
            const serviceCount = this.state.selectedProducts.length;

            if (serviceCount === 1) {
                setupTotal = this.pricing.setupFees['1'] || 0;
            } else if (serviceCount === 2) {
                setupTotal = this.pricing.setupFees['2'] || 0;
            } else if (serviceCount >= 3) {
                setupTotal = this.pricing.setupFees['3+'] || 0;
            }

            console.log(`[calculatePricing] Setup fee for ${serviceCount} service(s): ${setupTotal} cents`);

            // Step 2: Calculate weekly costs for selected products
            this.state.selectedProducts.forEach(productKey => {
                if (productKey === 'inbound_outbound_calls') {
                    // Call pricing depends on agent style (selected in step 5)
                    // We'll just note that calls are selected; actual per-minute rate comes from agentStyle
                    console.log('[calculatePricing] Calls selected (pricing depends on agent style)');
                    // No weekly base fee for calls - it's all usage-based
                }
                else if (productKey === 'emails') {
                    const emailPricing = this.pricing.products['emails'];
                    if (emailPricing) {
                        weeklyTotal += emailPricing.weekly || 0;
                        console.log(`[calculatePricing] Email weekly: ${emailPricing.weekly} cents`);
                    }
                }
                else if (productKey === 'chatbot') {
                    const chatPricing = this.pricing.products['chatbot'];
                    if (chatPricing) {
                        weeklyTotal += chatPricing.weekly || 0;
                        console.log(`[calculatePricing] Chat weekly: ${chatPricing.weekly} cents`);
                    }
                }
            });

            // Step 3: Calculate weekly costs for addons
            this.state.selectedAddons.forEach(addonKey => {
                const addon = this.pricing.addons[addonKey];
                if (addon) {
                    weeklyTotal += addon.weekly || 0;
                    console.log(`[calculatePricing] Addon '${addonKey}' weekly: ${addon.weekly} cents`);
                }
            });

            // Update pricing state
            this.pricing.setup = setupTotal;
            this.pricing.weekly = weeklyTotal;

            console.log('[calculatePricing] Final totals:', {
                setup: setupTotal,
                weekly: weeklyTotal,
                setupDollars: (setupTotal / 100).toFixed(2),
                weeklyDollars: (weeklyTotal / 100).toFixed(2)
            });

            // Update summary display
            this.updateSummary();
        }

        /**
         * Render current step
         */
        renderStep(stepNumber) {
            this.state.currentStep = stepNumber;
            const step = this.steps.find(s => s.id === stepNumber);

            if (step) {
                this.updateProgress();
                step.handler();
                this.updateSummary();
            }
        }

        /**
         * Update progress bar
         */
        updateProgress() {
            const progressFill = document.getElementById('aipwProgressFill');
            const progressSteps = document.getElementById('aipwProgressSteps');
            const progress = ((this.state.currentStep - 1) / (this.steps.length - 1)) * 100;

            progressFill.style.width = `${progress}%`;

            // Render step indicators
            progressSteps.innerHTML = this.steps.map(step => {
                let className = 'aipw-progress-step';
                if (step.id < this.state.currentStep) className += ' completed';
                if (step.id === this.state.currentStep) className += ' active';
                return `<div class="${className}">${step.name}</div>`;
            }).join('');
        }

        /**
         * Update summary sidebar
         */
        updateSummary() {
            const summaryContent = document.getElementById('aipwSummaryContent');

            let html = '';

            // Services
            if (this.state.selectedProducts.length > 0) {
                html += `
                    <div class="aipw-summary-section">
                        <div class="aipw-summary-section-title">Service(s)</div>
                        ${this.state.selectedProducts.map(p => `
                            <div class="aipw-summary-item">${this.getProductName(p)}</div>
                        `).join('')}
                    </div>
                `;
            }

            // Addons
            if (this.state.selectedAddons.length > 0) {
                html += `
                    <div class="aipw-summary-section">
                        <div class="aipw-summary-section-title">Addon(s)</div>
                        ${this.state.selectedAddons.map(a => `
                            <div class="aipw-summary-item">${a}</div>
                        `).join('')}
                    </div>
                `;
            }

            // Totals
            html += `
                <div class="aipw-summary-total">
                    <div class="aipw-summary-row">
                        <span>Setup Total</span>
                        <span>${this.formatCurrency(this.pricing.setup)}</span>
                    </div>
                    <div class="aipw-summary-row total">
                        <span>Weekly Cost</span>
                        <span>${this.formatCurrency(this.pricing.weekly)}</span>
                    </div>
                </div>
            `;

            summaryContent.innerHTML = html;
        }

        /**
         * Step 1: Product Selection
         */
        renderProductSelection() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            header.innerHTML = `
                <h1 class="aipw-modal-title">Customer2.AI Services</h1>
                <p class="aipw-modal-subtitle">Please make your service selection here</p>
            `;

            body.innerHTML = `
                <div class="aipw-products-grid">
                    <div class="aipw-product-card" data-product="inbound_outbound_calls">
                        <div class="aipw-product-icon">📞</div>
                        <div class="aipw-product-name">Inbound/Outbound Calls</div>
                        <div class="aipw-product-description">
                            Smart Call Routing<br>
                            Automated Call Handling<br>
                            Inbound & Outbound calls
                        </div>
                        ${this.getProductPricingHTML('inbound_outbound_calls')}
                        <div class="aipw-product-checkmark"></div>
                    </div>

                    <div class="aipw-product-card" data-product="emails">
                        <div class="aipw-product-icon">✉️</div>
                        <div class="aipw-product-name">Emails</div>
                        <div class="aipw-product-description">
                            Never Miss Emails<br>
                            Individual Responses<br>
                            AI That Replies
                        </div>
                        ${this.getProductPricingHTML('emails')}
                        <div class="aipw-product-checkmark"></div>
                    </div>

                    <div class="aipw-product-card" data-product="chatbot">
                        <div class="aipw-product-icon">💬</div>
                        <div class="aipw-product-name">Chatbot</div>
                        <div class="aipw-product-description">
                            Instant Customer Answers<br>
                            24/7 Chat Support<br>
                            AI That Engages
                        </div>
                        ${this.getProductPricingHTML('chatbot')}
                        <div class="aipw-product-checkmark"></div>
                    </div>
                </div>

                <div class="aipw-addons-section">
                    <h2 class="aipw-modal-title">Addons</h2>
                    <div class="aipw-addons-grid">
                        <div class="aipw-addon-item" data-addon="Quality Assurance">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Quality Assurance</div>
                        </div>
                        <div class="aipw-addon-item" data-addon="AVS Match">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">AVS Match</div>
                        </div>
                        <div class="aipw-addon-item" data-addon="Custom Packages">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Custom Packages</div>
                        </div>
                        <div class="aipw-addon-item" data-addon="Phone Numbers">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Phone Numbers</div>
                        </div>
                        <div class="aipw-addon-item" data-addon="Lead Verification">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Lead Verification</div>
                        </div>
                        <div class="aipw-addon-item" data-addon="Transcriptions & Recordings">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Transcriptions & Recordings</div>
                        </div>
                    </div>
                </div>

                <div class="aipw-terms-agreement">
                    <div class="aipw-terms-checkbox">
                        <input type="checkbox" id="aipwTermsCheckbox">
                        <label for="aipwTermsCheckbox">I Agree to Contract Terms Link</label>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.closeModal()">Cancel</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwPaymentBtn" disabled>Payment</button>
            `;

            // Attach product selection handlers
            this.attachProductHandlers();
        }

        /**
         * Attach product/addon selection handlers
         */
        attachProductHandlers() {
            // Product cards
            document.querySelectorAll('.aipw-product-card').forEach(card => {
                card.addEventListener('click', () => {
                    const product = card.dataset.product;
                    card.classList.toggle('selected');

                    if (card.classList.contains('selected')) {
                        if (!this.state.selectedProducts.includes(product)) {
                            this.state.selectedProducts.push(product);
                        }
                    } else {
                        this.state.selectedProducts = this.state.selectedProducts.filter(p => p !== product);
                    }

                    // Recalculate pricing based on selection
                    this.calculatePricing();
                    this.checkPaymentButtonState();
                });
            });

            // Addon items
            document.querySelectorAll('.aipw-addon-item').forEach(item => {
                item.addEventListener('click', () => {
                    const addon = item.dataset.addon;
                    item.classList.toggle('selected');

                    if (item.classList.contains('selected')) {
                        if (!this.state.selectedAddons.includes(addon)) {
                            this.state.selectedAddons.push(addon);
                        }
                    } else {
                        this.state.selectedAddons = this.state.selectedAddons.filter(a => a !== addon);
                    }

                    // Recalculate pricing based on selection
                    this.calculatePricing();
                });
            });

            // Terms checkbox
            document.getElementById('aipwTermsCheckbox').addEventListener('change', (e) => {
                this.state.termsAccepted = e.target.checked;
                this.checkPaymentButtonState();
            });

            // Payment button
            document.getElementById('aipwPaymentBtn').addEventListener('click', () => {
                this.renderStep(2);
            });
        }

        /**
         * Check if payment button should be enabled
         */
        checkPaymentButtonState() {
            const btn = document.getElementById('aipwPaymentBtn');
            const hasProducts = this.state.selectedProducts.length > 0;
            const hasTerms = this.state.termsAccepted;

            btn.disabled = !(hasProducts && hasTerms);
        }

        /**
         * Step 2: Payment Form
         */
        renderPaymentForm() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            header.innerHTML = `
                <h1 class="aipw-modal-title">Payment Information</h1>
                <p class="aipw-modal-subtitle">Enter your payment details</p>
            `;

            body.innerHTML = `
                <form class="aipw-payment-form" id="aipwPaymentForm">
                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">Personal Details</div>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">First Name</label>
                        <input type="text" class="aipw-form-input" name="first_name" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Last Name</label>
                        <input type="text" class="aipw-form-input" name="last_name" required>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Email Address</label>
                        <input type="email" class="aipw-form-input" name="email" required>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Phone Number</label>
                        <input type="tel" class="aipw-form-input" name="phone_number" required>
                    </div>

                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">Shipping Address</div>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Address</label>
                        <input type="text" class="aipw-form-input" name="shipping_address" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Country</label>
                        <select class="aipw-form-select" name="shipping_country" required>
                            <option value="">Select Country</option>
                            <option value="AF">Afghanistan</option>
                            <option value="AL">Albania</option>
                            <option value="DZ">Algeria</option>
                            <option value="AS">American Samoa</option>
                            <option value="AD">Andorra</option>
                            <option value="AO">Angola</option>
                            <option value="AI">Anguilla</option>
                            <option value="AQ">Antarctica</option>
                            <option value="AG">Antigua and Barbuda</option>
                            <option value="AR">Argentina</option>
                            <option value="AM">Armenia</option>
                            <option value="AW">Aruba</option>
                            <option value="AU">Australia</option>
                            <option value="AT">Austria</option>
                            <option value="AZ">Azerbaijan</option>
                            <option value="BS">Bahamas</option>
                            <option value="BH">Bahrain</option>
                            <option value="BD">Bangladesh</option>
                            <option value="BB">Barbados</option>
                            <option value="BY">Belarus</option>
                            <option value="BE">Belgium</option>
                            <option value="BZ">Belize</option>
                            <option value="BJ">Benin</option>
                            <option value="BM">Bermuda</option>
                            <option value="BT">Bhutan</option>
                            <option value="BO">Bolivia</option>
                            <option value="BA">Bosnia and Herzegovina</option>
                            <option value="BW">Botswana</option>
                            <option value="BR">Brazil</option>
                            <option value="IO">British Indian Ocean Territory</option>
                            <option value="BN">Brunei Darussalam</option>
                            <option value="BG">Bulgaria</option>
                            <option value="BF">Burkina Faso</option>
                            <option value="BI">Burundi</option>
                            <option value="KH">Cambodia</option>
                            <option value="CM">Cameroon</option>
                            <option value="CA">Canada</option>
                            <option value="CV">Cape Verde</option>
                            <option value="KY">Cayman Islands</option>
                            <option value="CF">Central African Republic</option>
                            <option value="TD">Chad</option>
                            <option value="CL">Chile</option>
                            <option value="CN">China</option>
                            <option value="CO">Colombia</option>
                            <option value="KM">Comoros</option>
                            <option value="CG">Congo</option>
                            <option value="CD">Congo, the Democratic Republic of the</option>
                            <option value="CR">Costa Rica</option>
                            <option value="CI">Côte d’Ivoire</option>
                            <option value="HR">Croatia</option>
                            <option value="CU">Cuba</option>
                            <option value="CY">Cyprus</option>
                            <option value="CZ">Czech Republic</option>
                            <option value="DK">Denmark</option>
                            <option value="DJ">Djibouti</option>
                            <option value="DM">Dominica</option>
                            <option value="DO">Dominican Republic</option>
                            <option value="EC">Ecuador</option>
                            <option value="EG">Egypt</option>
                            <option value="SV">El Salvador</option>
                            <option value="GQ">Equatorial Guinea</option>
                            <option value="ER">Eritrea</option>
                            <option value="EE">Estonia</option>
                            <option value="SZ">Eswatini</option>
                            <option value="ET">Ethiopia</option>
                            <option value="FJ">Fiji</option>
                            <option value="FI">Finland</option>
                            <option value="FR">France</option>
                            <option value="GF">French Guiana</option>
                            <option value="PF">French Polynesia</option>
                            <option value="GA">Gabon</option>
                            <option value="GM">Gambia</option>
                            <option value="GE">Georgia</option>
                            <option value="DE">Germany</option>
                            <option value="GH">Ghana</option>
                            <option value="GI">Gibraltar</option>
                            <option value="GR">Greece</option>
                            <option value="GL">Greenland</option>
                            <option value="GD">Grenada</option>
                            <option value="GP">Guadeloupe</option>
                            <option value="GU">Guam</option>
                            <option value="GT">Guatemala</option>
                            <option value="GG">Guernsey</option>
                            <option value="GN">Guinea</option>
                            <option value="GW">Guinea-Bissau</option>
                            <option value="GY">Guyana</option>
                            <option value="HT">Haiti</option>
                            <option value="VA">Holy See</option>
                            <option value="HN">Honduras</option>
                            <option value="HK">Hong Kong</option>
                            <option value="HU">Hungary</option>
                            <option value="IS">Iceland</option>
                            <option value="IN">India</option>
                            <option value="ID">Indonesia</option>
                            <option value="IR">Iran</option>
                            <option value="IQ">Iraq</option>
                            <option value="IE">Ireland</option>
                            <option value="IM">Isle of Man</option>
                            <option value="IL">Israel</option>
                            <option value="IT">Italy</option>
                            <option value="JM">Jamaica</option>
                            <option value="JP">Japan</option>
                            <option value="JE">Jersey</option>
                            <option value="JO">Jordan</option>
                            <option value="KZ">Kazakhstan</option>
                            <option value="KE">Kenya</option>
                            <option value="KI">Kiribati</option>
                            <option value="KP">Korea, Democratic People’s Republic of</option>
                            <option value="KR">Korea, Republic of</option>
                            <option value="KW">Kuwait</option>
                            <option value="KG">Kyrgyzstan</option>
                            <option value="LA">Lao People’s Democratic Republic</option>
                            <option value="LV">Latvia</option>
                            <option value="LB">Lebanon</option>
                            <option value="LS">Lesotho</option>
                            <option value="LR">Liberia</option>
                            <option value="LY">Libya</option>
                            <option value="LI">Liechtenstein</option>
                            <option value="LT">Lithuania</option>
                            <option value="LU">Luxembourg</option>
                            <option value="MO">Macao</option>
                            <option value="MG">Madagascar</option>
                            <option value="MW">Malawi</option>
                            <option value="MY">Malaysia</option>
                            <option value="MV">Maldives</option>
                            <option value="ML">Mali</option>
                            <option value="MT">Malta</option>
                            <option value="MH">Marshall Islands</option>
                            <option value="MQ">Martinique</option>
                            <option value="MR">Mauritania</option>
                            <option value="MU">Mauritius</option>
                            <option value="YT">Mayotte</option>
                            <option value="MX">Mexico</option>
                            <option value="FM">Micronesia</option>
                            <option value="MD">Moldova</option>
                            <option value="MC">Monaco</option>
                            <option value="MN">Mongolia</option>
                            <option value="ME">Montenegro</option>
                            <option value="MS">Montserrat</option>
                            <option value="MA">Morocco</option>
                            <option value="MZ">Mozambique</option>
                            <option value="MM">Myanmar</option>
                            <option value="NA">Namibia</option>
                            <option value="NR">Nauru</option>
                            <option value="NP">Nepal</option>
                            <option value="NL">Netherlands</option>
                            <option value="NC">New Caledonia</option>
                            <option value="NZ">New Zealand</option>
                            <option value="NI">Nicaragua</option>
                            <option value="NE">Niger</option>
                            <option value="NG">Nigeria</option>
                            <option value="NU">Niue</option>
                            <option value="NF">Norfolk Island</option>
                            <option value="MP">Northern Mariana Islands</option>
                            <option value="NO">Norway</option>
                            <option value="OM">Oman</option>
                            <option value="PK">Pakistan</option>
                            <option value="PW">Palau</option>
                            <option value="PS">Palestine, State of</option>
                            <option value="PA">Panama</option>
                            <option value="PG">Papua New Guinea</option>
                            <option value="PY">Paraguay</option>
                            <option value="PE">Peru</option>
                            <option value="PH">Philippines</option>
                            <option value="PL">Poland</option>
                            <option value="PT">Portugal</option>
                            <option value="PR">Puerto Rico</option>
                            <option value="QA">Qatar</option>
                            <option value="RE">Réunion</option>
                            <option value="RO">Romania</option>
                            <option value="RU">Russian Federation</option>
                            <option value="RW">Rwanda</option>
                            <option value="WS">Samoa</option>
                            <option value="SM">San Marino</option>
                            <option value="ST">Sao Tome and Principe</option>
                            <option value="SA">Saudi Arabia</option>
                            <option value="SN">Senegal</option>
                            <option value="RS">Serbia</option>
                            <option value="SC">Seychelles</option>
                            <option value="SL">Sierra Leone</option>
                            <option value="SG">Singapore</option>
                            <option value="SK">Slovakia</option>
                            <option value="SI">Slovenia</option>
                            <option value="SB">Solomon Islands</option>
                            <option value="SO">Somalia</option>
                            <option value="ZA">South Africa</option>
                            <option value="SS">South Sudan</option>
                            <option value="ES">Spain</option>
                            <option value="LK">Sri Lanka</option>
                            <option value="SD">Sudan</option>
                            <option value="SR">Suriname</option>
                            <option value="SE">Sweden</option>
                            <option value="CH">Switzerland</option>
                            <option value="SY">Syrian Arab Republic</option>
                            <option value="TW">Taiwan</option>
                            <option value="TJ">Tajikistan</option>
                            <option value="TZ">Tanzania</option>
                            <option value="TH">Thailand</option>
                            <option value="TL">Timor-Leste</option>
                            <option value="TG">Togo</option>
                            <option value="TK">Tokelau</option>
                            <option value="TO">Tonga</option>
                            <option value="TT">Trinidad and Tobago</option>
                            <option value="TN">Tunisia</option>
                            <option value="TR">Turkey</option>
                            <option value="TM">Turkmenistan</option>
                            <option value="TC">Turks and Caicos Islands</option>
                            <option value="TV">Tuvalu</option>
                            <option value="UG">Uganda</option>
                            <option value="UA">Ukraine</option>
                            <option value="AE">United Arab Emirates</option>
                            <option value="GB">United Kingdom</option>
                            <option value="US">United States</option>
                            <option value="UY">Uruguay</option>
                            <option value="UZ">Uzbekistan</option>
                            <option value="VU">Vanuatu</option>
                            <option value="VE">Venezuela</option>
                            <option value="VN">Viet Nam</option>
                            <option value="VG">Virgin Islands, British</option>
                            <option value="VI">Virgin Islands, U.S.</option>
                            <option value="EH">Western Sahara</option>
                            <option value="YE">Yemen</option>
                            <option value="ZM">Zambia</option>
                            <option value="ZW">Zimbabwe</option>
                        </select>
                        </div>


                    <div class="aipw-form-group">
                        <label class="aipw-form-label">City</label>
                        <input type="text" class="aipw-form-input" name="shipping_city" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">State</label>
                        <input type="text" class="aipw-form-input" name="shipping_state" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">ZIP</label>
                        <input type="text" class="aipw-form-input" name="shipping_zip" required>
                    </div>

                    <div class="aipw-form-checkbox-group full-width">
                        <input type="checkbox" id="aipwUseSameAddress" checked>
                        <label for="aipwUseSameAddress">Use my shipping address for billing</label>
                    </div>

                    <div id="aipwBillingAddress" style="display:none; grid-column: span 2;">
                        <div class="aipw-form-section">
                            <div class="aipw-form-section-title">Billing Address</div>
                        </div>

                        <div class="aipw-form-group full-width">
                            <label class="aipw-form-label">Address</label>
                            <input type="text" class="aipw-form-input" name="billing_address">
                        </div>

                        <div class="aipw-form-group">
                            <label class="aipw-form-label">Country</label>
                            <select class="aipw-form-select" name="billing_country">
                            <option value="">Select Country</option>
                            <option value="AF">Afghanistan</option>
                            <option value="AL">Albania</option>
                            <option value="DZ">Algeria</option>
                            <option value="AS">American Samoa</option>
                            <option value="AD">Andorra</option>
                            <option value="AO">Angola</option>
                            <option value="AI">Anguilla</option>
                            <option value="AQ">Antarctica</option>
                            <option value="AG">Antigua and Barbuda</option>
                            <option value="AR">Argentina</option>
                            <option value="AM">Armenia</option>
                            <option value="AW">Aruba</option>
                            <option value="AU">Australia</option>
                            <option value="AT">Austria</option>
                            <option value="AZ">Azerbaijan</option>
                            <option value="BS">Bahamas</option>
                            <option value="BH">Bahrain</option>
                            <option value="BD">Bangladesh</option>
                            <option value="BB">Barbados</option>
                            <option value="BY">Belarus</option>
                            <option value="BE">Belgium</option>
                            <option value="BZ">Belize</option>
                            <option value="BJ">Benin</option>
                            <option value="BM">Bermuda</option>
                            <option value="BT">Bhutan</option>
                            <option value="BO">Bolivia</option>
                            <option value="BA">Bosnia and Herzegovina</option>
                            <option value="BW">Botswana</option>
                            <option value="BR">Brazil</option>
                            <option value="IO">British Indian Ocean Territory</option>
                            <option value="BN">Brunei Darussalam</option>
                            <option value="BG">Bulgaria</option>
                            <option value="BF">Burkina Faso</option>
                            <option value="BI">Burundi</option>
                            <option value="KH">Cambodia</option>
                            <option value="CM">Cameroon</option>
                            <option value="CA">Canada</option>
                            <option value="CV">Cape Verde</option>
                            <option value="KY">Cayman Islands</option>
                            <option value="CF">Central African Republic</option>
                            <option value="TD">Chad</option>
                            <option value="CL">Chile</option>
                            <option value="CN">China</option>
                            <option value="CO">Colombia</option>
                            <option value="KM">Comoros</option>
                            <option value="CG">Congo</option>
                            <option value="CD">Congo, the Democratic Republic of the</option>
                            <option value="CR">Costa Rica</option>
                            <option value="CI">Côte d’Ivoire</option>
                            <option value="HR">Croatia</option>
                            <option value="CU">Cuba</option>
                            <option value="CY">Cyprus</option>
                            <option value="CZ">Czech Republic</option>
                            <option value="DK">Denmark</option>
                            <option value="DJ">Djibouti</option>
                            <option value="DM">Dominica</option>
                            <option value="DO">Dominican Republic</option>
                            <option value="EC">Ecuador</option>
                            <option value="EG">Egypt</option>
                            <option value="SV">El Salvador</option>
                            <option value="GQ">Equatorial Guinea</option>
                            <option value="ER">Eritrea</option>
                            <option value="EE">Estonia</option>
                            <option value="SZ">Eswatini</option>
                            <option value="ET">Ethiopia</option>
                            <option value="FJ">Fiji</option>
                            <option value="FI">Finland</option>
                            <option value="FR">France</option>
                            <option value="GF">French Guiana</option>
                            <option value="PF">French Polynesia</option>
                            <option value="GA">Gabon</option>
                            <option value="GM">Gambia</option>
                            <option value="GE">Georgia</option>
                            <option value="DE">Germany</option>
                            <option value="GH">Ghana</option>
                            <option value="GI">Gibraltar</option>
                            <option value="GR">Greece</option>
                            <option value="GL">Greenland</option>
                            <option value="GD">Grenada</option>
                            <option value="GP">Guadeloupe</option>
                            <option value="GU">Guam</option>
                            <option value="GT">Guatemala</option>
                            <option value="GG">Guernsey</option>
                            <option value="GN">Guinea</option>
                            <option value="GW">Guinea-Bissau</option>
                            <option value="GY">Guyana</option>
                            <option value="HT">Haiti</option>
                            <option value="VA">Holy See</option>
                            <option value="HN">Honduras</option>
                            <option value="HK">Hong Kong</option>
                            <option value="HU">Hungary</option>
                            <option value="IS">Iceland</option>
                            <option value="IN">India</option>
                            <option value="ID">Indonesia</option>
                            <option value="IR">Iran</option>
                            <option value="IQ">Iraq</option>
                            <option value="IE">Ireland</option>
                            <option value="IM">Isle of Man</option>
                            <option value="IL">Israel</option>
                            <option value="IT">Italy</option>
                            <option value="JM">Jamaica</option>
                            <option value="JP">Japan</option>
                            <option value="JE">Jersey</option>
                            <option value="JO">Jordan</option>
                            <option value="KZ">Kazakhstan</option>
                            <option value="KE">Kenya</option>
                            <option value="KI">Kiribati</option>
                            <option value="KP">Korea, Democratic People’s Republic of</option>
                            <option value="KR">Korea, Republic of</option>
                            <option value="KW">Kuwait</option>
                            <option value="KG">Kyrgyzstan</option>
                            <option value="LA">Lao People’s Democratic Republic</option>
                            <option value="LV">Latvia</option>
                            <option value="LB">Lebanon</option>
                            <option value="LS">Lesotho</option>
                            <option value="LR">Liberia</option>
                            <option value="LY">Libya</option>
                            <option value="LI">Liechtenstein</option>
                            <option value="LT">Lithuania</option>
                            <option value="LU">Luxembourg</option>
                            <option value="MO">Macao</option>
                            <option value="MG">Madagascar</option>
                            <option value="MW">Malawi</option>
                            <option value="MY">Malaysia</option>
                            <option value="MV">Maldives</option>
                            <option value="ML">Mali</option>
                            <option value="MT">Malta</option>
                            <option value="MH">Marshall Islands</option>
                            <option value="MQ">Martinique</option>
                            <option value="MR">Mauritania</option>
                            <option value="MU">Mauritius</option>
                            <option value="YT">Mayotte</option>
                            <option value="MX">Mexico</option>
                            <option value="FM">Micronesia</option>
                            <option value="MD">Moldova</option>
                            <option value="MC">Monaco</option>
                            <option value="MN">Mongolia</option>
                            <option value="ME">Montenegro</option>
                            <option value="MS">Montserrat</option>
                            <option value="MA">Morocco</option>
                            <option value="MZ">Mozambique</option>
                            <option value="MM">Myanmar</option>
                            <option value="NA">Namibia</option>
                            <option value="NR">Nauru</option>
                            <option value="NP">Nepal</option>
                            <option value="NL">Netherlands</option>
                            <option value="NC">New Caledonia</option>
                            <option value="NZ">New Zealand</option>
                            <option value="NI">Nicaragua</option>
                            <option value="NE">Niger</option>
                            <option value="NG">Nigeria</option>
                            <option value="NU">Niue</option>
                            <option value="NF">Norfolk Island</option>
                            <option value="MP">Northern Mariana Islands</option>
                            <option value="NO">Norway</option>
                            <option value="OM">Oman</option>
                            <option value="PK">Pakistan</option>
                            <option value="PW">Palau</option>
                            <option value="PS">Palestine, State of</option>
                            <option value="PA">Panama</option>
                            <option value="PG">Papua New Guinea</option>
                            <option value="PY">Paraguay</option>
                            <option value="PE">Peru</option>
                            <option value="PH">Philippines</option>
                            <option value="PL">Poland</option>
                            <option value="PT">Portugal</option>
                            <option value="PR">Puerto Rico</option>
                            <option value="QA">Qatar</option>
                            <option value="RE">Réunion</option>
                            <option value="RO">Romania</option>
                            <option value="RU">Russian Federation</option>
                            <option value="RW">Rwanda</option>
                            <option value="WS">Samoa</option>
                            <option value="SM">San Marino</option>
                            <option value="ST">Sao Tome and Principe</option>
                            <option value="SA">Saudi Arabia</option>
                            <option value="SN">Senegal</option>
                            <option value="RS">Serbia</option>
                            <option value="SC">Seychelles</option>
                            <option value="SL">Sierra Leone</option>
                            <option value="SG">Singapore</option>
                            <option value="SK">Slovakia</option>
                            <option value="SI">Slovenia</option>
                            <option value="SB">Solomon Islands</option>
                            <option value="SO">Somalia</option>
                            <option value="ZA">South Africa</option>
                            <option value="SS">South Sudan</option>
                            <option value="ES">Spain</option>
                            <option value="LK">Sri Lanka</option>
                            <option value="SD">Sudan</option>
                            <option value="SR">Suriname</option>
                            <option value="SE">Sweden</option>
                            <option value="CH">Switzerland</option>
                            <option value="SY">Syrian Arab Republic</option>
                            <option value="TW">Taiwan</option>
                            <option value="TJ">Tajikistan</option>
                            <option value="TZ">Tanzania</option>
                            <option value="TH">Thailand</option>
                            <option value="TL">Timor-Leste</option>
                            <option value="TG">Togo</option>
                            <option value="TK">Tokelau</option>
                            <option value="TO">Tonga</option>
                            <option value="TT">Trinidad and Tobago</option>
                            <option value="TN">Tunisia</option>
                            <option value="TR">Turkey</option>
                            <option value="TM">Turkmenistan</option>
                            <option value="TC">Turks and Caicos Islands</option>
                            <option value="TV">Tuvalu</option>
                            <option value="UG">Uganda</option>
                            <option value="UA">Ukraine</option>
                            <option value="AE">United Arab Emirates</option>
                            <option value="GB">United Kingdom</option>
                            <option value="US">United States</option>
                            <option value="UY">Uruguay</option>
                            <option value="UZ">Uzbekistan</option>
                            <option value="VU">Vanuatu</option>
                            <option value="VE">Venezuela</option>
                            <option value="VN">Viet Nam</option>
                            <option value="VG">Virgin Islands, British</option>
                            <option value="VI">Virgin Islands, U.S.</option>
                            <option value="EH">Western Sahara</option>
                            <option value="YE">Yemen</option>
                            <option value="ZM">Zambia</option>
                            <option value="ZW">Zimbabwe</option>
                            </select>
                        </div>

                        <div class="aipw-form-group">
                            <label class="aipw-form-label">City</label>
                            <input type="text" class="aipw-form-input" name="billing_city">
                        </div>

                        <div class="aipw-form-group">
                            <label class="aipw-form-label">State / Province</label>
                            <input type="text" class="aipw-form-input" name="billing_state">
                        </div>

                        <div class="aipw-form-group">
                            <label class="aipw-form-label">ZIP / Postal Code</label>
                            <input type="text" class="aipw-form-input" name="billing_zip">
                        </div>
                    </div>

                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">Payment Method</div>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Name on Card</label>
                        <input type="text" class="aipw-form-input" name="card_name" required>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Card Information</label>
                        <div id="card-element" class="aipw-stripe-element"></div>
                        <div id="card-errors" class="aipw-stripe-errors" role="alert"></div>
                    </div>
                </form>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(1)">Back</button>
                <button class="aipw-btn aipw-btn-primary" onclick="aipwWidget.submitPayment()">Submit Payment</button>
            `;

            // Toggle billing address
            document.getElementById('aipwUseSameAddress').addEventListener('change', (e) => {
                document.getElementById('aipwBillingAddress').style.display =
                    e.target.checked ? 'none' : 'grid';
            });

            // Initialize Stripe Elements
            this.initializeStripe();
        }

        /**
         * Initialize Stripe Elements
         */
        initializeStripe() {
            if (!window.Stripe) {
                console.error('Stripe.js not loaded');
                return;
            }

            try {
                // Initialize Stripe
                this.stripe = Stripe(this.config.stripePublicKey);
                const elements = this.stripe.elements();

                // Create card element with simpler, more compatible styles
                this.cardElement = elements.create('card', {
                    style: {
                        base: {
                            color: '#32325d',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            fontSmoothing: 'antialiased',
                            fontSize: '16px',
                            '::placeholder': {
                                color: '#aab7c4'
                            }
                        },
                        invalid: {
                            color: '#fa755a',
                            iconColor: '#fa755a'
                        }
                    }
                });

                // Mount card element
                const mountResult = this.cardElement.mount('#card-element');

                // Log mount result for debugging
                console.log('Stripe Element mounted:', mountResult);

                // Handle real-time validation errors
                this.cardElement.on('change', (event) => {
                    const displayError = document.getElementById('card-errors');
                    if (displayError) {
                        if (event.error) {
                            displayError.textContent = event.error.message;
                        } else {
                            displayError.textContent = '';
                        }
                    }
                    console.log('Card change event:', event);
                });

                // Handle ready event
                this.cardElement.on('ready', () => {
                    console.log('Stripe Element ready for input');
                });

            } catch (error) {
                console.error('Error initializing Stripe:', error);
                alert('Failed to initialize payment form. Please refresh the page.');
            }
        }

        /**
         * Submit payment
         */
        async submitPayment() {
            const form = document.getElementById('aipwPaymentForm');
            const formData = new FormData(form);

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Verify Stripe and card element are initialized
            if (!this.stripe || !this.cardElement) {
                alert('Payment system not initialized. Please refresh the page.');
                return;
            }

            try {
                console.log('Creating Stripe token...');

                // Create Stripe token BEFORE showing loading (to avoid unmounting the element)
                const result = await this.stripe.createToken(this.cardElement);

                console.log('Stripe token result:', result);

                if (result.error) {
                    // Show error to user
                    const errorElement = document.getElementById('card-errors');
                    if (errorElement) {
                        errorElement.textContent = result.error.message;
                    } else {
                        alert('Card error: ' + result.error.message);
                    }
                    console.error('Stripe error:', result.error);
                    return;
                }

                if (!result.token) {
                    alert('Failed to create payment token. Please try again.');
                    console.error('No token returned from Stripe');
                    return;
                }

                console.log('Token created successfully:', result.token.id);

                // Create PaymentMethod BEFORE showing loading (to avoid unmounting the element)
                console.log('Creating Stripe PaymentMethod...');
                const pmResult = await this.stripe.createPaymentMethod({
                    type: "card",
                    card: this.cardElement
                });
                console.log('PaymentMethod result:', pmResult);

                if (pmResult.error) {
                    console.error('PaymentMethod creation error:', pmResult.error);
                    alert('Payment method creation failed: ' + pmResult.error.message);
                    return;
                }

                if (!pmResult.paymentMethod || !pmResult.paymentMethod.id) {
                    console.error('No PaymentMethod ID returned');
                    alert('Failed to create payment method. Please try again.');
                    return;
                }

                console.log('PaymentMethod ID created:', pmResult.paymentMethod.id);

                // Store payment info for later
                this.state.paymentInfo = Object.fromEntries(formData);
                this.state.paymentInfo.stripe_token = result.token.id;
                this.state.paymentInfo.card_token = pmResult.paymentMethod.id;

                // Handle billing address - if checkbox is checked or billing is empty, use shipping
                const useSameAddress = document.getElementById('aipwUseSameAddress')?.checked ?? true;
                if (useSameAddress || !this.state.paymentInfo.billing_address) {
                    this.state.paymentInfo.billing_address = this.state.paymentInfo.shipping_address;
                    this.state.paymentInfo.billing_city = this.state.paymentInfo.shipping_city;
                    this.state.paymentInfo.billing_state = this.state.paymentInfo.shipping_state;
                    this.state.paymentInfo.billing_zip = this.state.paymentInfo.shipping_zip;
                    this.state.paymentInfo.billing_country = this.state.paymentInfo.shipping_country;
                    console.log('Billing address set to shipping address');
                }

                console.log('Payment info stored:', this.state.paymentInfo);

                // Show success message
                this.showPaymentSuccess();

                // Wait briefly to show success message
                await new Promise(resolve => setTimeout(resolve, 1500));

                // Check if calls selected
                if (this.state.selectedProducts.includes('inbound_outbound_calls')) {
                    this.renderStep(3); // Go to call setup
                } else {
                    await this.completeOrder();
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('Payment error: ' + error.message);
                this.renderStep(2);
            }
        }

        /**
         * Step 3: Call Setup Configuration
         */
        renderCallSetup() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            header.innerHTML = `
                <h1 class="aipw-modal-title">Inbound and Outbound Calls</h1>
                <p class="aipw-modal-subtitle">How would you like to setup your agent phone number(s)?</p>
            `;

            body.innerHTML = `
                <div class="aipw-config-options">
                    <div class="aipw-config-card" data-setup="purchase">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Purchase Number</div>
                        <div class="aipw-config-description">Get a new phone number</div>
                    </div>

                    <div class="aipw-config-card" data-setup="byo">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">BYONumber (Porting)</div>
                        <div class="aipw-config-description">Port your existing number</div>
                    </div>

                    <div class="aipw-config-card" data-setup="forwarding">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Forwarding</div>
                        <div class="aipw-config-description">Forward to existing number</div>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(2)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwSetupNextBtn" disabled>Next</button>
            `;

            // Setup selection handler
            document.querySelectorAll('.aipw-config-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-config-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.setupType = card.dataset.setup;
                    document.getElementById('aipwSetupNextBtn').disabled = false;
                });
            });

            document.getElementById('aipwSetupNextBtn').addEventListener('click', () => {
                this.renderStep(4);
            });
        }

        /**
         * Step 4: Configuration Details
         */
        renderConfiguration() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            const setupTypeText = {
                'purchase': 'Purchase Number',
                'byo': 'BYONumber (Porting)',
                'forwarding': 'Forwarding'
            }[this.state.setupType] || '';

            header.innerHTML = `
                <h1 class="aipw-modal-title">Inbound and Outbound Calls: ${setupTypeText}</h1>
                <p class="aipw-modal-subtitle">Configure your setup</p>
            `;

            body.innerHTML = `
                <div class="aipw-number-input-group">
                    <label class="aipw-form-label">How many lines/numbers would you like?</label>
                    <input type="number" class="aipw-form-input" id="aipwNumberCount" value="1" min="1" style="max-width: 200px;">
                </div>

                <div class="aipw-assignment-options">
                    <div class="aipw-config-card" data-assignment="single">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Assign Single Agent to All Service Numbers</div>
                    </div>

                    <div class="aipw-config-card" data-assignment="individual">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Assign an Agent to Each Number</div>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(3)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwConfigNextBtn" disabled>Next</button>
            `;

            // Assignment selection handler
            document.querySelectorAll('.aipw-assignment-options .aipw-config-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-assignment-options .aipw-config-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.assignmentType = card.dataset.assignment;
                    this.state.numberCount = parseInt(document.getElementById('aipwNumberCount').value);
                    document.getElementById('aipwConfigNextBtn').disabled = false;
                });
            });

            document.getElementById('aipwConfigNextBtn').addEventListener('click', () => {
                this.renderStep(5);
            });
        }

        /**
         * Step 5: Agent Style Selection
         */
        renderAgentStyle() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            header.innerHTML = `
                <h1 class="aipw-modal-title">Inbound and Outbound Calls: Select Agent Style</h1>
                <p class="aipw-modal-subtitle">Which level of AI support best fits where your business is today?</p>
            `;

            // Get pricing for each agent style
            const quickPricing = this.pricing.agentStyles['Quick'] || {};
            const advancedPricing = this.pricing.agentStyles['Advanced'] || {};
            const conversationalPricing = this.pricing.agentStyles['Conversational'] || {};

            body.innerHTML = `
                <div class="aipw-agent-styles">
                    <div class="aipw-agent-card" data-agent="quick">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-agent-name">Quick</div>
                        <div class="aipw-agent-description">Streamlines common requests and workflows</div>
                        <div class="aipw-agent-pricing">${this.formatCurrency(quickPricing.phone_per_minute || 0)}/min</div>
                    </div>

                    <div class="aipw-agent-card" data-agent="advanced">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-agent-name">Advanced</div>
                        <div class="aipw-agent-description">Adapts across channels with richer context</div>
                        <div class="aipw-agent-pricing">${this.formatCurrency(advancedPricing.phone_per_minute || 0)}/min</div>
                    </div>

                    <div class="aipw-agent-card" data-agent="conversational">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-agent-name">Conversational</div>
                        <div class="aipw-agent-description">Manages complex cases with full customer awareness</div>
                        <div class="aipw-agent-pricing">${this.formatCurrency(conversationalPricing.phone_per_minute || 0)}/min</div>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(4)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwCompleteBtn" disabled>Complete</button>
            `;

            // Agent selection handler
            document.querySelectorAll('.aipw-agent-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-agent-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.agentStyle = card.dataset.agent;
                    document.getElementById('aipwCompleteBtn').disabled = false;
                });
            });

            document.getElementById('aipwCompleteBtn').addEventListener('click', () => {
                this.completeOrder();
            });
        }

        /**
         * Complete the order
         */
        async completeOrder() {
            console.log('=== ORDER COMPLETION STARTED ===');

            this.showLoading('Completing your order...');

            try {
                // Get agent style pricing if calls were selected
                let agentStylePricing = null;
                if (this.state.selectedProducts.includes('inbound_outbound_calls') && this.state.agentStyle) {
                    // Capitalize first letter to match pricing key (Quick, Advanced, Conversational)
                    const styleKey = this.state.agentStyle.charAt(0).toUpperCase() + this.state.agentStyle.slice(1);
                    agentStylePricing = this.pricing.agentStyles[styleKey] || null;
                }

                // Prepare complete order payload
                let orderData = {
                    // Products and addons
                    products: this.state.selectedProducts,
                    addons: this.state.selectedAddons,

                    // Pricing
                    setup_total: this.pricing.setup,
                    weekly_cost: this.pricing.weekly,

                    // Payment info
                    payment: this.state.paymentInfo,

                    // Call setup (if applicable)
                    call_setup: this.state.selectedProducts.includes('inbound_outbound_calls') ? {
                        setup_type: this.state.setupType,
                        number_count: this.state.numberCount,
                        assignment_type: this.state.assignmentType,
                        agent_style: this.state.agentStyle,
                        agent_style_pricing: agentStylePricing // Include pricing details for backend
                    } : null
                };

                console.log('Order data prepared:', JSON.stringify(orderData, null, 2));

                // Send to n8n webhook via ApiProxy
                console.log('Calling complete_order API...');
                const response = await this.apiCall('complete_order', orderData);

                console.log('Complete order response:', response);

                if (response.success) {
                    console.log('Order completed successfully!');
                    this.showSuccess();

                    // If BYO/Porting, send LOA via email
                    if (this.state.setupType === 'byo') {
                        console.log('Setup type is BYO, sending porting LOA...');
                        await this.apiCall('send_porting_loa', {
                            email: this.state.paymentInfo.email,
                            customer_name: `${this.state.paymentInfo.first_name} ${this.state.paymentInfo.last_name}`,
                            number_count: this.state.numberCount
                        });
                        console.log('Porting LOA sent');
                    }
                } else {
                    console.error('Order completion failed:', response);
                    console.error('Error details:', {
                        error: response.error,
                        error_code: response.error_code,
                        data: response.data,
                        full_response: response
                    });
                    alert('Order completion failed: ' + (response.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Exception during order completion:', error);
                console.error('Error stack:', error.stack);
                alert('Error: ' + error.message);
            }

            console.log('=== ORDER COMPLETION ENDED ===');
        }

        /**
         * Show loading state
         */
        showLoading(message) {
            const body = document.getElementById('aipwModalBody');
            body.innerHTML = `
                <div class="aipw-loading">
                    <div class="aipw-spinner"></div>
                    <p>${message}</p>
                </div>
            `;
        }

        /**
         * Show payment success message
         */
        showPaymentSuccess() {
            const body = document.getElementById('aipwModalBody');
            body.innerHTML = `
                <div class="aipw-success">
                    <div class="aipw-success-icon">✓</div>
                    <h2 class="aipw-success-title">Payment Verified!</h2>
                    <p class="aipw-success-message">
                        Your payment information has been securely verified.
                    </p>
                </div>
            `;
        }

        /**
         * Show success message
         */
        showSuccess() {
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            body.innerHTML = `
                <div class="aipw-success">
                    <div class="aipw-success-icon">✓</div>
                    <h2 class="aipw-success-title">Order Complete!</h2>
                    <p class="aipw-success-message">
                        Thank you for your order. You will receive a confirmation email shortly.
                    </p>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn aipw-btn-primary" onclick="aipwWidget.closeModal()">Close</button>
            `;
        }

        /**
         * API call helper (uses proxy)
         */
        async apiCall(action, data) {
            console.log(`[apiCall] Starting ${action} request`);
            console.log('[apiCall] Request data:', data);

            const requestBody = {
                action: 'aipw_' + action,
                nonce: this.config.nonce,
                data: data
            };

            console.log('[apiCall] Request body:', requestBody);
            console.log('[apiCall] API endpoint:', this.config.apiProxy);

            try {
                const response = await fetch(this.config.apiProxy, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                console.log(`[apiCall] Response status: ${response.status} ${response.statusText}`);

                if (!response.ok) {
                    console.error('[apiCall] Response not OK:', response);
                    const errorText = await response.text();
                    console.error('[apiCall] Error response body:', errorText);
                    throw new Error(`API call failed: ${response.status} ${response.statusText}`);
                }

                const jsonResponse = await response.json();
                console.log(`[apiCall] ${action} response:`, jsonResponse);

                return jsonResponse;
            } catch (error) {
                console.error(`[apiCall] Exception in ${action}:`, error);
                throw error;
            }
        }

        /**
         * Get product display name
         */
        getProductName(slug) {
            const names = {
                'inbound_outbound_calls': 'Inbound/Outbound Calls',
                'emails': 'Emails',
                'chatbot': 'Chatbot'
            };
            return names[slug] || slug;
        }

        /**
         * Get product pricing HTML for display in product card
         */
        getProductPricingHTML(productKey) {
            if (productKey === 'inbound_outbound_calls') {
                // Call pricing is usage-based and depends on agent style
                const quickPricing = this.pricing.agentStyles['Quick'] || {};
                const advancedPricing = this.pricing.agentStyles['Advanced'] || {};
                const conversationalPricing = this.pricing.agentStyles['Conversational'] || {};

                return `
                    <div class="aipw-product-pricing">
                        <div class="aipw-pricing-title" style="font-weight: 600; margin-bottom: 6px; font-size: 12px;">Usage-Based Pricing:</div>
                        <div class="aipw-pricing-tier" style="font-size: 11px; margin-bottom: 2px;">
                            Quick: ${this.formatCurrency(quickPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-tier" style="font-size: 11px; margin-bottom: 2px;">
                            Advanced: ${this.formatCurrency(advancedPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-tier" style="font-size: 11px; margin-bottom: 2px;">
                            Conversational: ${this.formatCurrency(conversationalPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-note" style="font-size: 10px; opacity: 0.7; margin-top: 4px; font-style: italic;">
                            Rate selected later
                        </div>
                    </div>
                `;
            }

            const pricing = this.pricing.products[productKey];

            if (!pricing) {
                return '<div class="aipw-product-pricing">Pricing loading...</div>';
            }

            const weeklyFormatted = this.formatCurrency(pricing.weekly || 0);

            // Build pricing details with thresholds and overages
            let pricingHTML = `
                <div class="aipw-product-pricing">
                    <div class="aipw-pricing-weekly" style="font-weight: 600; margin-bottom: 6px;">${weeklyFormatted}/week</div>
            `;

            // Add threshold and overage information if available
            if (productKey === 'emails' && pricing.email_threshold) {
                pricingHTML += `
                    <div class="aipw-pricing-details" style="font-size: 10px; opacity: 0.8;">
                        <div style="margin-bottom: 2px;">Includes ${pricing.email_threshold} emails/week</div>
                        ${pricing.email_cost_overage ? `<div>Overage: ${this.formatCurrency(pricing.email_cost_overage)}/email</div>` : ''}
                    </div>
                `;
            } else if (productKey === 'chatbot' && pricing.chat_threshold) {
                pricingHTML += `
                    <div class="aipw-pricing-details" style="font-size: 10px; opacity: 0.8;">
                        <div style="margin-bottom: 2px;">Includes ${pricing.chat_threshold} chats/week</div>
                        ${pricing.chat_cost_overage ? `<div>Overage: ${this.formatCurrency(pricing.chat_cost_overage)}/chat</div>` : ''}
                    </div>
                `;
            }

            pricingHTML += `</div>`;

            return pricingHTML;
        }

        /**
         * Format currency value
         */
        formatCurrency(cents) {
            const dollars = cents / 100;
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(dollars);
        }
    }

    // Initialize widget when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.aipwWidget = new AIProductsWidget(window.aipwConfig || {});
        });
    } else {
        window.aipwWidget = new AIProductsWidget(window.aipwConfig || {});
    }

})();
