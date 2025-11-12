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


            // Load saved state from localStorage or use defaults
            this.state = this.loadState() || {
                currentStep: 1,
                selectedProducts: [],
                selectedAddons: [],
                setupType: null,
                numberCount: 0,
                assignmentType: null,
                phoneNumberType: null,
                agentQuality: null,
                paymentInfo: {},
                termsAccepted: false,
                portingPhoneNumbers: [], // Array of {phone_number, service_provider}
                loaFormData: {}, // LOA form data (business_name, signature, etc.)
                userId: null, // User ID from create_user API
                utilityBillBase64: null,
                utilityBillFilename: null,
                utilityBillMimeType: null,
                utilityBillExtension: null,
                phoneCountPricingTotal: 0,
                couponCode: '', // Coupon code for discounted pricing
                salesGeneratedId: '' // Default pricing ID
            };

            this.steps = [
                { id: 1, name: 'Products', handler: this.renderProductSelection.bind(this) },
                { id: 2, name: 'Setup Inbound/Outbound Calls', handler: this.renderCallSetup.bind(this) },
                { id: 3, name: 'Configure Numbers', handler: this.renderConfiguration.bind(this) },
                { id: 4, name: 'Payment', handler: this.renderPaymentForm.bind(this) },
                { id: 5, name: 'Porting LOA', handler: this.renderPortingLOA.bind(this) }
            ];

            // Pricing - will be loaded from n8n API
            this.pricing = {
                setup: 0,
                weekly: 0,
                products: {},  // Product-specific pricing
                addons: {},     // Addon-specific pricing
                setupFees: {}, // Setup fees by service count (1, 2, 3+)
                agentQuality: {}, // Agent style pricing by type (Quick, Advanced, Conversational)
                phoneNumberWeeklyCost: 0
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
         * Load state from localStorage
         * @returns {Object|null} Saved state or null if not found
         */
        loadState() {
            try {
                const savedState = localStorage.getItem('aipw_widget_state');
                if (savedState) {
                    const parsed = JSON.parse(savedState);
                    // Normalize phone_numbers to portingPhoneNumbers for backward compatibility
                    if (parsed.phone_numbers && !parsed.portingPhoneNumbers) {
                        parsed.portingPhoneNumbers = parsed.phone_numbers;
                    }
                    return parsed;
                }
            } catch (error) {
                console.error('[loadState] Error loading state from localStorage:', error);
            }
            return null;
        }

        /**
         * Save current state to localStorage
         */
        saveState() {
            try {
                // Create a clean copy of state without sensitive payment info
                const stateToSave = {
                    currentStep: this.state.currentStep,
                    selectedProducts: this.state.selectedProducts,
                    selectedAddons: this.state.selectedAddons,
                    setupType: this.state.setupType,
                    numberCount: this.state.numberCount,
                    assignmentType: this.state.assignmentType,
                    phoneNumberType: this.state.phoneNumberType,
                    agentQuality: this.state.agentQuality,
                    termsAccepted: this.state.termsAccepted,
                    // Save payment info but exclude sensitive card data
                    paymentInfo: {
                        first_name: this.state.paymentInfo.first_name,
                        last_name: this.state.paymentInfo.last_name,
                        email: this.state.paymentInfo.email,
                        phone_number: this.state.paymentInfo.phone_number,
                        shipping_address: this.state.paymentInfo.shipping_address,
                        shipping_city: this.state.paymentInfo.shipping_city,
                        shipping_state: this.state.paymentInfo.shipping_state,
                        shipping_zip: this.state.paymentInfo.shipping_zip,
                        shipping_country: this.state.paymentInfo.shipping_country,
                        billing_address: this.state.paymentInfo.billing_address,
                        billing_city: this.state.paymentInfo.billing_city,
                        billing_state: this.state.paymentInfo.billing_state,
                        billing_zip: this.state.paymentInfo.billing_zip,
                        billing_country: this.state.paymentInfo.billing_country
                        // Do NOT save: stripe_token, card_token, or any card details
                    },
                    userId: this.state.userId,
                    phone_numbers: this.state.portingPhoneNumbers
                };

                localStorage.setItem('aipw_widget_state', JSON.stringify(stateToSave));
                // Don't log sensitive data in production
                // console.log('[saveState] State saved to localStorage ', stateToSave);
            } catch (error) {
                console.error('[saveState] Error saving state to localStorage:', error);
            }
        }

        /**
         * Clear saved state from localStorage
         */
        clearState() {
            try {
                localStorage.removeItem('aipw_widget_state');
            } catch (error) {
                console.error('[clearState] Error clearing state from localStorage:', error);
            }
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

                        <div class="aipw-summary" id="aipwSummary">
                            <div class="aipw-summary-title">Summary</div>
                            <div id="aipwSummaryContent"></div>
                        </div>
                    </div>

                    
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
            this.modal = document.getElementById('aipwModal');
        }

        /**
         * Apply coupon code and reload pricing
         */
        async applyCoupon() {
            const couponInput = document.getElementById('aipwCouponCode');
            const messageDiv = document.getElementById('aipwCouponMessage');
            const couponCode = couponInput.value.trim();

            if (!couponCode) {
                messageDiv.innerHTML = '<span style="color: red;">Please enter a coupon code</span>';
                return;
            }

            try {
                messageDiv.innerHTML = '<span style="color: #666;">Validating coupon...</span>';

                // Call API to validate coupon and get sales_generated_id
                const response = await this.apiCall('validate_coupon', { coupon_code: couponCode });

                if (response.success && response.data && response.data.sales_generated_id) {
                    // Update state with new pricing ID and coupon code
                    this.state.salesGeneratedId = response.data.sales_generated_id;
                    this.state.couponCode = couponCode;
                    this.saveState();

                    // Reload pricing with new sales_generated_id
                    await this.loadPricing(response.data.sales_generated_id);

                    // Recalculate pricing
                    this.calculatePricing();

                    messageDiv.innerHTML = '<span style="color: green;">✓ Coupon applied successfully!</span>';
                    couponInput.disabled = true;
                    document.getElementById('aipwApplyCoupon').disabled = true;
                } else {
                    messageDiv.innerHTML = '<span style="color: red;">Invalid coupon code</span>';
                }
            } catch (error) {
                console.error('Coupon validation error:', error);
                messageDiv.innerHTML = '<span style="color: red;">Error validating coupon</span>';
            }
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

            // Render the saved step (or step 1 if no saved state)
            const stepToRender = this.state.currentStep || 1;
            this.renderStep(stepToRender);
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
         * @param {string} salesGeneratedId - The sales_generated_id to fetch pricing for
         */
        async loadPricing(salesGeneratedId = null, couponCode = null) {
            try {
                const pricingId = salesGeneratedId || this.state.salesGeneratedId;
                const response = await this.apiCall('get_pricing', { sales_generated_id: pricingId ?? '', coupon_code: couponCode });

                // Handle the response structure: response.data is an array with cost_json inside
                const costData = response.success && response.data && response.data[0] && response.data[0].cost_json;

                if (costData) {

                    // Parse rates - they come as numbers or dollar strings like "$99.00" or "$0.45"
                    // Convert to cents (integer) for calculations
                    const parseRate = (rateStr) => {
                        if (!rateStr) return 0;
                        if (typeof rateStr === 'number') return Math.round(rateStr * 100);
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

                        }

                        // Handle Inbound/Outbound Calls (agent style pricing)
                        else if ((name === 'Inbound Calls' || name === 'Outbound Calls') && frequency === 'Weekly') {
                            const phone_per_minute = parseRate(item.phone_per_minute);
                            const phone_per_minute_overage = parseRate(item.phone_per_minute_overage);
                            const call_threshold = (item.call_threshold && typeof item.call_threshold == "number") ? item.call_threshold : 0;

                            // Store by agent style type (Quick, Advanced, Conversational)
                            const styleKey = type; // "Quick", "Advanced", or "Conversational"

                            if (!this.pricing.agentQuality[styleKey]) {
                                this.pricing.agentQuality[styleKey] = {
                                    name: styleKey,
                                    description: item.description || '',
                                    phone_per_minute: 0,
                                    phone_per_minute_overage: 0,
                                    call_threshold: 0
                                };
                            }

                            // Combine Inbound and Outbound (they should have same rates)
                            this.pricing.agentQuality[styleKey].phone_per_minute = phone_per_minute;
                            this.pricing.agentQuality[styleKey].phone_per_minute_overage = phone_per_minute_overage;
                            this.pricing.agentQuality[styleKey].call_threshold = call_threshold;

                            // Update description if provided
                            if (item.description) {
                                this.pricing.agentQuality[styleKey].description = item.description;
                            }

                        }

                        // Handle Email Agents
                        else if (name === 'Email Agents' && frequency === 'Weekly') {
                            const cost = parseRate(item.cost);
                            const email_threshold = (item.email_threshold && typeof item.email_threshold == "number") ? item.email_threshold : 0;
                            const email_cost_overage = parseRate(item.email_cost_overage);

                            this.pricing.products['emails'] = {
                                weekly: cost,
                                email_threshold: email_threshold,
                                email_cost_overage: email_cost_overage,
                                type: type, // "Basic"
                                frequency: frequency
                            };

                        }

                        // Handle Chat Agents
                        else if (name === 'Chat Agents' && frequency === 'Weekly') {
                            const cost = parseRate(item.cost);
                            const chat_threshold = (item.chat_threshold && typeof item.chat_threshold == "number") ? item.chat_threshold : 0;
                            const chat_cost_overage = parseRate(item.chat_cost_overage);

                            this.pricing.products['chatbot'] = {
                                weekly: cost,
                                chat_threshold: chat_threshold,
                                chat_cost_overage: chat_cost_overage,
                                type: type, // "Basic"
                                frequency: frequency
                            };

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

                        }
                        // Handle Phone Number pricing
                        else if (type === 'Price Per Number' && frequency === 'Weekly') {
                            const cost_per_number = parseRate(item.cost_per_number);

                            this.pricing.phoneNumberWeeklyCost = cost_per_number;
                        }
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


            // Step 1: Calculate setup fee based on number of selected services
            const serviceCount = this.state.selectedProducts.length;

            if (serviceCount === 1) {
                setupTotal = this.pricing.setupFees['1'] || 0;
            } else if (serviceCount === 2) {
                setupTotal = this.pricing.setupFees['2'] || 0;
            } else if (serviceCount >= 3) {
                setupTotal = this.pricing.setupFees['3+'] || 0;
            }

            // Step 2: Calculate weekly costs for selected products
            this.state.selectedProducts.forEach(productKey => {
                if (productKey === 'inbound_outbound_calls') {
                    
                }
                else if (productKey === 'emails') {
                    const emailPricing = this.pricing.products['emails'];
                    if (emailPricing) {
                        weeklyTotal += emailPricing.weekly || 0;
                    }
                }
                else if (productKey === 'chatbot') {
                    const chatPricing = this.pricing.products['chatbot'];
                    if (chatPricing) {
                        weeklyTotal += chatPricing.weekly || 0;
                    }
                }
            });

            // Step 3: Calculate weekly costs for addons
            this.state.selectedAddons.forEach(addonKey => {
                const addon = this.pricing.addons[addonKey];
                if (addon) {
                    weeklyTotal += addon.weekly || 0;
                }
            });

            weeklyTotal += this.state.numberCount * this.pricing.phoneNumberWeeklyCost || 0;

            // Update pricing state
            this.pricing.setup = setupTotal;
            this.pricing.weekly = weeklyTotal;
            this.pricing.total = setupTotal + weeklyTotal;


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

                // Save state to localStorage when navigating between steps
                this.saveState();
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

            // Agent Quality
            if (this.state.agentQuality) {
                const styleKey = this.state.agentQuality.charAt(0).toUpperCase() + this.state.agentQuality.slice(1);
                const agentQualityPricing = this.pricing.agentQuality[styleKey] || {};
                const pricePerMinute = agentQualityPricing.phone_per_minute || 0;

                html += `
                    <div class="aipw-summary-section">
                        <div class="aipw-summary-section-title">Agent Quality</div>
                        <div class="aipw-summary-item">
                            <span>${styleKey}</span>
                            <span style="margin-left: auto; font-weight: 500;">${this.formatCurrency(pricePerMinute)}/min</span>
                        </div>
                    </div>
                `;
            }

            // Addons
            if (this.state.selectedAddons.length > 0) {
                html += `
                    <div class="aipw-summary-section">
                        <div class="aipw-summary-section-title">Addon(s)</div>
                        ${this.state.selectedAddons.map(a => {
                            // Get addon pricing
                            let price = 0;
                            let unit = '/week';

                            if (a === 'Phone Numbers') {
                                price = this.pricing.phoneNumberWeeklyCost || 0;
                                unit = '/number/week';
                            } else if (this.pricing.addons[a]) {
                                price = this.pricing.addons[a].weekly || 0;
                            } else if (a === 'Lead Verification') {
                                unit = '/lead';
                            }

                            // Special badge for Transcriptions & Recordings
                            const freeBadge = a === 'Transcriptions & Recordings'
                                ? '<span style="margin-left: 8px; padding: 2px 8px; background: #4CAF50; color: white; font-size: 11px; border-radius: 4px; font-weight: 600;">90 Days Free</span>'
                                : '';

                            return `
                                <div class="aipw-summary-item" style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>${a}${freeBadge}</span>
                                    <span style="margin-left: 10px; font-weight: 500; white-space: nowrap;">${this.formatCurrency(price)}${unit}</span>
                                </div>
                            `;
                        }).join('')}
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
                            <div style="display: flex; align-items: center;">
                                <div class="aipw-addon-checkbox"></div>
                                <div class="aipw-addon-name">Quality Assurance</div>
                            </div>
                            ${this.getAddonPricingHTML('Quality Assurance')}
                        </div>
                        <div class="aipw-addon-item" data-addon="AVS Match">
                            <div style="display: flex; align-items: center;">
                                <div class="aipw-addon-checkbox"></div>
                                <div class="aipw-addon-name">AVS Match</div>
                            </div>
                            ${this.getAddonPricingHTML('AVS Match')}
                        </div>
                        <div class="aipw-addon-item" data-addon="Custom Packages">
                            <div style="display: flex; align-items: center;">
                                <div class="aipw-addon-checkbox"></div>
                                <div class="aipw-addon-name">Custom Packages</div>
                            </div>
                            ${this.getAddonPricingHTML('Custom Packages')}
                        </div>
                        <div class="aipw-addon-item" data-addon="Phone Numbers">
                        <div style="display: flex; align-items: center;">
                            <div class="aipw-addon-checkbox"></div>
                            <div class="aipw-addon-name">Phone Numbers</div>
                            </div>
                            ${this.getAddonPricingHTML('Phone Numbers')}
                        </div>
                        <div class="aipw-addon-item" data-addon="Lead Verification">
                            <div style="display: flex; align-items: center;">
                                <div class="aipw-addon-checkbox"></div>
                                <div class="aipw-addon-name">Lead Verification</div>
                            </div>
                            ${this.getAddonPricingHTML('Lead Verification')}
                        </div>
                        <div class="aipw-addon-item" data-addon="Transcriptions & Recordings">
                            <div style="display: flex; align-items: center;">
                                <div class="aipw-addon-checkbox"></div>
                                <div class="aipw-addon-name">Transcriptions & Recordings</div>
                            </div>
                            ${this.getAddonPricingHTML('Transcriptions & Recordings')}
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

            // Restore UI state from saved selections
            this.restoreProductSelectionState();
        }

        /**
         * Restore product selection UI state from saved data
         */
        restoreProductSelectionState() {
            // Restore selected products
            this.state.selectedProducts.forEach(productKey => {
                const card = document.querySelector(`.aipw-product-card[data-product="${productKey}"]`);
                if (card) {
                    card.classList.add('selected');
                }
            });

            // Restore selected addons
            this.state.selectedAddons.forEach(addonKey => {
                const item = document.querySelector(`.aipw-addon-item[data-addon="${addonKey}"]`);
                if (item) {
                    item.classList.add('selected');
                }
            });

            // Restore terms checkbox
            const termsCheckbox = document.getElementById('aipwTermsCheckbox');
            if (termsCheckbox) {
                termsCheckbox.checked = this.state.termsAccepted || false;
            }

            // Update button state
            this.checkPaymentButtonState();
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

                    // Save state to localStorage
                    this.saveState();
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

                    // Save state to localStorage
                    this.saveState();
                });
            });


            // Terms checkbox
            document.getElementById('aipwTermsCheckbox').addEventListener('change', (e) => {
                this.state.termsAccepted = e.target.checked;
                this.checkPaymentButtonState();

                // Save state to localStorage
                this.saveState();
            });

            // Next button - go to Setup if calls selected, otherwise Payment
            document.getElementById('aipwPaymentBtn').addEventListener('click', () => {
                if (this.state.selectedProducts.includes('inbound_outbound_calls')) {
                    this.renderStep(2); // Go to Setup
                } else {
                    this.renderStep(4); // Go to Payment (skip Setup & Configuration)
                }
            });
        }


        /**
         * Check if payment button should be enabled
         */
        checkPaymentButtonState() {
            const btn = document.getElementById('aipwPaymentBtn');
            const hasProducts = this.state.selectedProducts.length > 0;
            const hasTerms = this.state.termsAccepted;
            const hasCalls = this.state.selectedProducts.includes('inbound_outbound_calls');

            // Update button text based on whether calls are selected
            btn.textContent = hasCalls ? 'Next' : 'Payment';
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

                    <div class="aipw-coupon-section full-width" style="margin: 30px 0;">
                        <h3 class="aipw-form-section-title" style="margin-bottom: 15px;">Have a Coupon Code?</h3>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="aipwCouponCode" placeholder="Enter coupon code"
                                style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px;">
                            <button id="aipwApplyCoupon" class="aipw-btn aipw-btn-primary" style="white-space: nowrap;">Apply Coupon</button>
                        </div>
                        <div id="aipwCouponMessage" style="margin-top: 10px; font-size: 14px;"></div>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Name on Card</label>
                        <input type="text" class="aipw-form-input" name="card_name" required>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Card Information</label>
                        <div id="aipw-card-element" class="aipw-stripe-element"></div>
                        <div id="aipw-card-errors" class="aipw-stripe-errors" role="alert"></div>
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
            // Coupon code application
            document.getElementById('aipwApplyCoupon').addEventListener('click', async () => {
                await this.applyCoupon();
            });

            // Initialize Stripe Elements
            this.initializeStripe();

            // Restore form data from saved state
            this.restorePaymentFormState();
        }

        /**
         * Restore payment form state from saved data
         */
        restorePaymentFormState() {
            const form = document.getElementById('aipwPaymentForm');
            if (!form || !this.state.paymentInfo) return;

            const fields = [
                'first_name', 'last_name', 'email', 'phone_number',
                'shipping_address', 'shipping_city', 'shipping_state',
                'shipping_zip', 'shipping_country', 'billing_address', 'billing_city', 'billing_state',
                'billing_zip', 'billing_country'
            ];

            fields.forEach(fieldName => {
                const input = form.querySelector(`[name="${fieldName}"]`);
                if (input && this.state.paymentInfo[fieldName]) {
                    input.value = this.state.paymentInfo[fieldName];
                }
            });

            console.log('[restorePaymentFormState] Payment form restored from localStorage');
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

                // Create card element with simpler, more compatible Quality
                this.cardElement = elements.create('card', {
                    style: {
                        base: {
                            color: '#ffffffff',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            fontSmoothing: 'antialiased',
                            fontSize: '16px',
                            '::placeholder': {
                                color: '#cedbe9ff'
                            }
                        },
                        invalid: {
                            color: '#fa755a',
                            iconColor: '#fa755a'
                        }
                    }
                });

                // Mount card element
                const mountResult = this.cardElement.mount('#aipw-card-element');

                // Handle real-time validation errors
                this.cardElement.on('change', (event) => {
                    const displayError = document.getElementById('aipw-card-errors');
                    if (displayError) {
                        if (event.error) {
                            displayError.textContent = event.error.message;
                        } else {
                            displayError.textContent = '';
                        }
                    }
                });

                // Handle ready event
                this.cardElement.on('ready', () => {
                });

            } catch (error) {
                console.error('Error initializing Stripe:', error);
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
                console.error('Payment system not initialized. Please refresh the page.');
                return;
            }

            try {

                // Create Stripe token BEFORE showing loading (to avoid unmounting the element)
                const result = await this.stripe.createToken(this.cardElement);


                if (result.error) {
                    // Show error to user
                    const errorElement = document.getElementById('card-errors');
                    if (errorElement) {
                        errorElement.textContent = result.error.message;
                    } else {
                        console.error('Card error: ' + result.error.message);
                    }
                    console.error('Stripe error:', result.error);
                    return;
                }

                if (!result.token) {
                    console.error('No token returned from Stripe');
                    return;
                }


                // Create PaymentMethod BEFORE showing loading (to avoid unmounting the element)
                const pmResult = await this.stripe.createPaymentMethod({
                    type: "card",
                    card: this.cardElement
                });

                if (pmResult.error) {
                    console.error('PaymentMethod creation error:', pmResult.error);
                    return;
                }

                if (!pmResult.paymentMethod || !pmResult.paymentMethod.id) {
                    console.error('No PaymentMethod ID returned');
                    return;
                }


                // Store payment info for later
                this.state.paymentInfo = Object.fromEntries(formData);
                

                // Handle billing address - if checkbox is checked or billing is empty, use shipping
                const useSameAddress = document.getElementById('aipwUseSameAddress')?.checked ?? true;
                if (useSameAddress || !this.state.paymentInfo.billing_address) {
                    this.state.paymentInfo.billing_address = this.state.paymentInfo.shipping_address;
                    this.state.paymentInfo.billing_city = this.state.paymentInfo.shipping_city;
                    this.state.paymentInfo.billing_state = this.state.paymentInfo.shipping_state;
                    this.state.paymentInfo.billing_zip = this.state.paymentInfo.shipping_zip;
                    this.state.paymentInfo.billing_country = this.state.paymentInfo.shipping_country;
                }

                // Log payment info without sensitive tokens
                const { card_token, stripe_token, ...safePaymentInfo } = this.state.paymentInfo;

                // Save state to localStorage (payment info without sensitive card data)
                this.saveState();
                this.calculatePricing();


                //user created here
                const chargeCustomer = await this.apiCall('charge_customer', {
                    first_name: this.state.paymentInfo.first_name,
                    last_name: this.state.paymentInfo.last_name,
                    email: this.state.paymentInfo.email,
                    phone_number: this.state.paymentInfo.phone_number,
                    card_token: pmResult.paymentMethod.id,
                    stripe_token: result.token.id,
                    shipping_address: this.state.paymentInfo.shipping_address,
                    shipping_city: this.state.paymentInfo.shipping_city,
                    shipping_state: this.state.paymentInfo.shipping_state,
                    shipping_zip: this.state.paymentInfo.shipping_zip,
                    shipping_country: this.state.paymentInfo.shipping_country,
                    billing_address: this.state.paymentInfo.billing_address,
                    billing_city: this.state.paymentInfo.billing_city,
                    billing_state: this.state.paymentInfo.billing_state,
                    billing_zip: this.state.paymentInfo.billing_zip,
                    billing_country: this.state.paymentInfo.billing_country,
                    total_to_charge: this.pricing.setup,
                    payment_info: this.state.paymentInfo,
                    products: this.state.selectedProducts,
                    sales_generated_id: this.state.salesGeneratedId
                });
                if (chargeCustomer.success === false) {
                    throw new Error(chargeCustomer.message || 'Payment failed');
                } else {
                }

                // Show success message
                this.showPaymentSuccess();

                // Wait briefly to show success message
                await new Promise(resolve => setTimeout(resolve, 1500));


                this.state.userId = chargeCustomer.data;
                this.saveState();


                // Check if BYO porting is selected - if so, show LOA form
                if (this.state.setupType === 'byo') {
                    this.renderStep(5); // Show LOA form (Porting LOA is step 5)
                } else {
                    await this.completeOrder(); // Complete order directly
                }
            } catch (error) {
                console.error('Payment error:', error);
                this.renderStep(4); // Back to Payment
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

                <!-- Agent Quality Section -->
                <div id="aipwAgentQualitySection" style="display: none; margin-top: 40px; padding-top: 40px; border-top: 2px solid var(--border);">
                    <h2 class="aipw-form-section-title">Agent Quality</h2>
                    <p class="aipw-modal-subtitle" style="margin-bottom: 20px;">Choose the quality level for your AI agent</p>
                    <div class="aipw-agent-styles" id="aipwAgentQualityOptions">
                        <!-- Agent quality cards will be inserted here -->
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(1)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwSetupNextBtn" disabled>Next</button>
            `;

            // Setup selection handler
            document.querySelectorAll('.aipw-config-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-config-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.setupType = card.dataset.setup;

                    // Show Agent Quality section after setup type is selected
                    this.showAgentQualitySection();

                    // Enable next button only if agent quality is also selected
                    this.updateSetupNextButton();

                    // Save state to localStorage
                    this.saveState();
                });
            });

            document.getElementById('aipwSetupNextBtn').addEventListener('click', () => {
                this.renderStep(3); // Go to Configuration
            });

            // Restore saved selection
            this.restoreCallSetupState();
        }

        /**
         * Show Agent Quality section within Setup step
         */
        showAgentQualitySection() {
            const section = document.getElementById('aipwAgentQualitySection');
            const optionsContainer = document.getElementById('aipwAgentQualityOptions');


            if (!section || !optionsContainer) {
                console.warn('[showAgentQualitySection] Elements not found, returning early');
                return;
            }

            // Show the section
            section.style.display = 'block';

            // Populate agent quality options from pricing data
            const agentQuality = this.pricing.agentQuality || {};

            let agentQualityHTML = '';

            for (const [key, style] of Object.entries(agentQuality)) {
                const phonePerMinute = style.phone_per_minute || 0;
                const phonePerMinuteOverage = style.phone_per_minute_overage || 0;

                agentQualityHTML += `
                    <div class="aipw-agent-card" data-style="${key}">
                        <div class="aipw-agent-name">${style.name || key}</div>
                        <div class="aipw-agent-description">${style.description || ''}</div>
                        <div class="aipw-product-pricing">
                            <div class="aipw-pricing-setup">Per Minute: ${this.formatCurrency(phonePerMinute)}</div>
                            <div class="aipw-pricing-weekly">Overage: ${this.formatCurrency(phonePerMinuteOverage)}/min</div>
                        </div>
                    </div>
                `;
            }


            optionsContainer.innerHTML = agentQualityHTML;

            // Add click handlers for agent quality cards
            document.querySelectorAll('.aipw-agent-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-agent-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.agentQuality = card.dataset.style;

                    // Update next button state
                    this.updateSetupNextButton();

                    // Save state and recalculate pricing
                    this.saveState();
                    this.calculatePricing();
                });
            });

            // Restore saved agent quality selection
            if (this.state.agentQuality) {
                const savedCard = document.querySelector(`.aipw-agent-card[data-style="${this.state.agentQuality}"]`);
                if (savedCard) {
                    savedCard.classList.add('selected');
                }
            }
        }

        /**
         * Update Setup Next button state based on selections
         */
        updateSetupNextButton() {
            const nextBtn = document.getElementById('aipwSetupNextBtn');
            if (!nextBtn) return;

            // Enable next button if setup type is selected AND agent quality is selected
            const setupSelected = !!this.state.setupType;
            const agentQualitySelected = !!this.state.agentQuality;

            nextBtn.disabled = !(setupSelected && agentQualitySelected);
        }

        /**
         * Restore call setup state from saved data
         */
        restoreCallSetupState() {
            if (this.state.setupType) {
                const card = document.querySelector(`.aipw-config-card[data-setup="${this.state.setupType}"]`);
                if (card) {
                    card.classList.add('selected');

                    // Show Agent Quality section if setup type was previously selected
                    this.showAgentQualitySection();

                    // Update next button state
                    this.updateSetupNextButton();
                }
            }
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
                <div class="aipw-number-type-selection">
                    <div class="aipw-config-card" data-assignment="toll_free">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Toll-Free</div>
                    </div>

                    <div class="aipw-config-card" data-assignment="direct_dial">
                        <div class="aipw-config-radio"></div>
                        <div class="aipw-config-title">Direct Dial (DID)</div>
                    </div>
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
                <button class="aipw-btn" onclick="aipwWidget.renderStep(2)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwConfigNextBtn" disabled>Next</button>
            `;

            // Assignment selection handler
            document.querySelectorAll('.aipw-assignment-options .aipw-config-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-assignment-options .aipw-config-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.assignmentType = card.dataset.assignment;
                    document.getElementById('aipwConfigNextBtn').disabled = false;

                    // Save state to localStorage
                    this.saveState();
                });
            });

            document.querySelectorAll('.aipw-number-type-selection .aipw-config-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('.aipw-number-type-selection .aipw-config-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    this.state.phoneNumberType = card.dataset.assignment;
                    document.getElementById('aipwConfigNextBtn').disabled = false;

                    // Save state to localStorage
                    this.saveState();
                });
            });

            document.getElementById('aipwNumberCount').addEventListener('change', (e) => {
                this.state.numberCount = parseInt(e.target.value);
                this.state.phoneCountPricingTotal = this.pricing.phoneNumberWeeklyCost * this.state.numberCount;

                this.saveState();
                this.calculatePricing();
            });

            document.getElementById('aipwConfigNextBtn').addEventListener('click', () => {
                this.renderStep(4); // Go to Payment
            });

            // Restore saved selection
            this.restoreConfigurationState();
        }

        /**
         * Restore configuration state from saved data
         */
        restoreConfigurationState() {
            if (this.state.assignmentType) {
                const card = document.querySelector(`.aipw-assignment-options .aipw-config-card[data-assignment="${this.state.assignmentType}"]`);
                if (card) {
                    card.classList.add('selected');
                    document.getElementById('aipwConfigNextBtn').disabled = false;
                }
            }

            if (this.state.phoneNumberType) {
                const card_phone = document.querySelector(`.aipw-number-type-selection .aipw-config-card[data-assignment="${this.state.phoneNumberType}"]`);
                if (card_phone) {
                    card_phone.classList.add('selected');
                    document.getElementById('aipwConfigNextBtn').disabled = false;
                }
            }

            if (this.state.numberCount) {
                const input = document.getElementById('aipwNumberCount');
                if (input) {
                    input.value = this.state.numberCount;
                }
            }
        }

        /**
         * Step 5: Porting LOA Form (BYO only)
         */
        renderPortingLOA() {
            const header = document.getElementById('aipwModalHeader');
            const body = document.getElementById('aipwModalBody');
            const footer = document.getElementById('aipwModalFooter');

            header.innerHTML = `
                <h1 class="aipw-modal-title">Porting Letter of Authorization</h1>
                <p class="aipw-modal-subtitle">Please complete and sign the form to authorize porting your phone number(s)</p>
                <p class="aipw-modal-sub-subtitle"><b>Legal authorization:</b> The LOA serves as a legally binding document that protects you from unauthorized transfers and prevents your old provider from blocking the porting process.</p>
                <p class="aipw-modal-sub-subtitle"><b>Verification of ownership:</b> It proves you are the authorized owner of the phone number and allows your new carrier to act on your behalf.</p>
                <p class="aipw-modal-sub-subtitle"><b>Information transfer:</b> It permits your new provider to access necessary billing and account information from your old provider to complete the switch</p>
            `;

            // Generate phone number input fields based on numberCount
            let phoneNumberFields = '';
            for (let i = 0; i < this.state.numberCount; i++) {
                const rowNumber = i + 1;
                phoneNumberFields += `
                    <div class="aipw-phone-number-row">
                        <div class="aipw-form-group">
                            <label class="aipw-form-label">Phone Number ${rowNumber}*</label>
                            <input type="tel" class="aipw-form-input" name="phone_number_${i}"
                                   placeholder="555888900${i}" required
                                   data-phone-index="${i}">
                        </div>
                        <div class="aipw-form-group">
                            <label class="aipw-form-label">Service Provider*</label>
                            <input type="text" class="aipw-form-input" name="service_provider_${i}"
                                   placeholder="e.g., Verizon, AT&T" required
                                   data-provider-index="${i}">
                        </div>
                    </div>
                `;
            }

            body.innerHTML = `
                <form class="aipw-loa-form" id="aipwLOAForm">
                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">1. Customer Name (as it appears on your telephone bill)</div>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">First Name*</label>
                        <input type="text" class="aipw-form-input" name="first_name"
                               value="${this.state.paymentInfo.first_name || ''}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Last Name*</label>
                        <input type="text" class="aipw-form-input" name="last_name"
                               value="${this.state.paymentInfo.last_name || ''}" required>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Business Name (if service is in your company's name)</label>
                        <input type="text" class="aipw-form-input" name="business_name"
                               value="">
                    </div>

                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">2. Service Address on file with your current carrier</div>
                        <div class="aipw-form-help-text">(Must be a physical location, cannot be a PO Box)</div>
                    </div>

                    <div class="aipw-form-group full-width">
                        <label class="aipw-form-label">Address*</label>
                        <input type="text" class="aipw-form-input" name="address"
                               value="${this.state.paymentInfo.shipping_address || ''}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">City*</label>
                        <input type="text" class="aipw-form-input" name="city"
                               value="${this.state.paymentInfo.shipping_city || ''}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">State*</label>
                        <input type="text" class="aipw-form-input" name="state"
                               value="${this.state.paymentInfo.shipping_state || ''}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">ZIP/Postal Code*</label>
                        <input type="text" class="aipw-form-input" name="zip"
                               value="${this.state.paymentInfo.shipping_zip || ''}" required>
                    </div>

                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">3. List all Telephone Number(s) to be ported</div>
                    </div>

                    ${phoneNumberFields}

                    <div class="aipw-form-section">
                        <div class="aipw-form-section-title">Authorization</div>
                    </div>

                    <div class="aipw-authorization-text">
                        <p>By signing below, I verify that I am, or represent (for a business), the above-named service customer,
                        authorized to change the primary carrier(s) for the telephone number(s) listed, and am at least 18 years of age.
                        The name and address I have provided is the name and address on record with my local telephone company
                        for each telephone number listed. I authorize <strong>Customer2.AI</strong> (the "Company") or its
                        designated agent to act on my behalf and notify my current carrier(s) to change my preferred carrier(s) for the
                        listed number(s) and service(s), to obtain any information the Company deems necessary to make the carrier
                        change(s), including, for example, an inventory of telephone lines billed to the telephone number(s), carrier or
                        customer identifying information, billing addresses, and my credit history.</p>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Authorized Signature*</label>
                        <div class="aipw-signature-pad-container">
                            <canvas id="aipwSignaturePad" class="aipw-signature-pad"></canvas>
                            <button type="button" class="aipw-btn aipw-btn-clear-signature" id="aipwClearSignature">Clear</button>
                        </div>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Printed Name*</label>
                        <input type="text" class="aipw-form-input" name="printed_name"
                               value="${this.state.paymentInfo.first_name || ''} ${this.state.paymentInfo.last_name || ''}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Date*</label>
                        <input type="date" class="aipw-form-input" name="date"
                               value="${new Date().toISOString().split('T')[0]}" required>
                    </div>

                    <div class="aipw-form-group">
                        <label class="aipw-form-label">Utility Bill Upload*</label>
                        <input type="file" class="aipw-form-input" id="aipwUtilityBillUpload"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="aipw-form-help">Please upload a recent utility bill (PDF or image). Max size: 5MB</small>
                        <div id="aipwUtilityBillPreview" class="aipw-file-preview" style="display:none;">
                            <span id="aipwUtilityBillName"></span>
                            <button type="button" class="aipw-btn-remove-file" onclick="aipwWidget.clearUtilityBill()">Remove</button>
                        </div>
                    </div>

                    <div class="aipw-form-note">
                        <strong>Note:</strong> For toll free numbers, please change RespOrg to TWI01.
                        Please do not end service on the number for 10 days after RespOrg change.
                    </div>
                </form>
            `;

            footer.innerHTML = `
                <button class="aipw-btn" onclick="aipwWidget.renderStep(5)">Back</button>
                <button class="aipw-btn aipw-btn-primary" id="aipwSubmitLOA">Submit LOA & Complete Order</button>
            `;

            // Initialize signature pad
            this.initializeSignaturePad();

            // Attach form handlers
            this.attachLOAHandlers();

            // Restore saved phone numbers if any
            this.restorePortingPhoneNumbers();

            // Restore utility bill preview if any
            this.restoreUtilityBillPreview();
        }

        /**
         * Restore utility bill preview from state
         */
        restoreUtilityBillPreview() {
            if (this.state.utilityBillFilename) {
                const preview = document.getElementById('aipwUtilityBillPreview');
                const nameSpan = document.getElementById('aipwUtilityBillName');

                if (preview && nameSpan) {
                    nameSpan.textContent = this.state.utilityBillFilename;
                    preview.style.display = 'block';
                }
            }
        }

        /**
         * Initialize signature pad for LOA form
         */
        initializeSignaturePad() {
            const canvas = document.getElementById('aipwSignaturePad');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Set canvas size
            canvas.width = canvas.offsetWidth;
            canvas.height = 150;

            // Drawing state
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            // Style
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            // Mouse events
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                const rect = canvas.getBoundingClientRect();
                lastX = e.clientX - rect.left;
                lastY = e.clientY - rect.top;
            });

            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();

                lastX = x;
                lastY = y;
            });

            canvas.addEventListener('mouseup', () => {
                isDrawing = false;
            });

            canvas.addEventListener('mouseleave', () => {
                isDrawing = false;
            });

            // Touch events for mobile
            canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                isDrawing = true;
                const rect = canvas.getBoundingClientRect();
                const touch = e.touches[0];
                lastX = touch.clientX - rect.left;
                lastY = touch.clientY - rect.top;
            });

            canvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                if (!isDrawing) return;
                const rect = canvas.getBoundingClientRect();
                const touch = e.touches[0];
                const x = touch.clientX - rect.left;
                const y = touch.clientY - rect.top;

                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();

                lastX = x;
                lastY = y;
            });

            canvas.addEventListener('touchend', () => {
                isDrawing = false;
            });

            // Store canvas reference
            this.signaturePad = canvas;
        }

        /**
         * Attach LOA form handlers
         */
        attachLOAHandlers() {
            // Clear signature button
            document.getElementById('aipwClearSignature').addEventListener('click', () => {
                const canvas = document.getElementById('aipwSignaturePad');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });

            // Submit LOA button
            document.getElementById('aipwSubmitLOA').addEventListener('click', () => {
                this.submitPortingLOA();
            });


            // Save phone numbers on blur
            const form = document.getElementById('aipwLOAForm');
            form.addEventListener('change', () => {
                this.capturePortingPhoneNumbers();
                this.saveState();
            });

            // Attach listeners to phone number and provider inputs
            for (let i = 0; i < this.state.numberCount; i++) {
                const phoneInput = document.querySelector(`input[data-phone-index="${i}"]`);
                const providerInput = document.querySelector(`input[data-provider-index="${i}"]`);

                if (phoneInput) {
                    phoneInput.addEventListener('input', () => {
                        this.capturePortingPhoneNumbers();
                    });
                }

                if (providerInput) {
                    providerInput.addEventListener('input', () => {
                        this.capturePortingPhoneNumbers();
                    });
                }
            }

            this.handleUtilityBillUpload();


        }

        /**
         * Handle utility bill file upload
         */
        handleUtilityBillUpload() {
            const fileInput = document.getElementById('aipwUtilityBillUpload');

            if (!fileInput) return;

            fileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];

                if (!file) return;

                // Validate file size (5MB max)
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size exceeds 5MB limit. Please choose a smaller file.');
                    fileInput.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Please upload a PDF or image file.');
                    fileInput.value = '';
                    return;
                }

                try {
                    // Convert to base64
                    const base64 = await this.fileToBase64(file);

                    // Store in state (remove data URI prefix)
                    this.state.utilityBillBase64 = base64.split(',')[1];
                    this.state.utilityBillFilename = file.name;
                    this.state.utilityBillMimeType = file.type;
                    this.state.utilityBillExtension = file.name.split('.').pop();

                    // Show preview
                    this.showUtilityBillPreview(file.name);

                    // Save state
                    this.saveState();

                } catch (error) {
                    console.error('[handleUtilityBillUpload] Error:', error);
                    alert('Error reading file. Please try again.');
                    fileInput.value = '';
                }
            });
        }

        /**
         * Convert file to base64
         */
        fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        /**
         * Show utility bill preview
         */
        showUtilityBillPreview(filename) {
            const preview = document.getElementById('aipwUtilityBillPreview');
            const nameSpan = document.getElementById('aipwUtilityBillName');

            if (preview && nameSpan) {
                nameSpan.textContent = filename;
                preview.style.display = 'block';
            }
        }

        /**
         * Clear utility bill upload
         */
        clearUtilityBill() {
            const fileInput = document.getElementById('aipwUtilityBillUpload');
            const preview = document.getElementById('aipwUtilityBillPreview');

            if (fileInput) fileInput.value = '';
            if (preview) preview.style.display = 'none';

            this.state.utilityBillBase64 = null;
            this.state.utilityBillFilename = null;
            this.state.utilityBillMimeType = null;
            this.state.utilityBillExtension = null;

            this.saveState();
        }

        /**
         * Capture porting phone numbers from form
         */
        capturePortingPhoneNumbers() {
            const phoneNumbers = [];
            for (let i = 0; i < this.state.numberCount; i++) {
                const phoneInput = document.querySelector(`input[data-phone-index="${i}"]`);
                const providerInput = document.querySelector(`input[data-provider-index="${i}"]`);

                if (phoneInput && providerInput) {
                    phoneNumbers.push({
                        phone_number: phoneInput.value,
                        service_provider: providerInput.value
                    });
                }
            }
            this.state.portingPhoneNumbers = phoneNumbers;
            this.saveState();
        }

        /**
         * Restore porting phone numbers to form
         */
        restorePortingPhoneNumbers() {
            if (!this.state.portingPhoneNumbers || this.state.portingPhoneNumbers.length === 0) {
                return;
            }

            this.state.portingPhoneNumbers.forEach((entry, i) => {
                const phoneInput = document.querySelector(`input[data-phone-index="${i}"]`);
                const providerInput = document.querySelector(`input[data-provider-index="${i}"]`);

                if (phoneInput && providerInput) {
                    phoneInput.value = entry.phone_number || '';
                    providerInput.value = entry.service_provider || '';
                }
            });
        }

        /**
         * Submit Porting LOA form
         */
        async submitPortingLOA() {
            const form = document.getElementById('aipwLOAForm');
            const canvas = document.getElementById('aipwSignaturePad');

            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Check if signature is drawn
            const ctx = canvas.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const hasSignature = imageData.data.some(channel => channel !== 0);

            if (!hasSignature) {
                alert('Please provide your signature');
                return;
            }



            try {
                // Capture phone numbers
                this.capturePortingPhoneNumbers();

                // Get signature as base64
                const signatureBase64 = canvas.toDataURL('image/png');

                // Get form data
                const formData = new FormData(form);
                this.state.loaFormData = {
                    business_name: "",
                    signature: signatureBase64,
                    printed_name: formData.get('printed_name'),
                    date: formData.get('date'),
                    numbers_to_port: this.state.portingPhoneNumbers
                };


                // Generate LOA form HTML for PDF generation
                const loaHTML = this.generateLOAHTML();

                // Submit LOA to database via API
                console.log('[submitPortingLOA] Submitting LOA form... ', this.state);
                const loaResult = await this.apiCall('submit_porting_loa', {
                    userId: this.state.userId,
                    loa_html: btoa(loaHTML),
                    numbers_to_port: this.state.portingPhoneNumbers,
                    paymentInfo: this.state.paymentInfo,
                    utility_bill_base64: this.state.utilityBillBase64,
                    utility_bill_filename: this.state.utilityBillFilename,
                    utility_bill_mime_type: this.state.utilityBillMimeType,
                    utility_bill_extension: this.state.utilityBillExtension,
                    sales_generated_id: this.state.salesGeneratedId
                });

                if (!loaResult.success) {
                    throw new Error('Failed to submit LOA form: ' + (loaResult.error || 'Unknown error'));
                }

                console.log('[submitPortingLOA] LOA submitted successfully');

                // Complete the order
                //await this.completeOrder();

            } catch (error) {
                console.error('[submitPortingLOA] Error:', error);
                this.renderStep(6); // Stay on LOA form
            }
        }

        /**
         * Generate LOA HTML for PDF generation (server-side)
         */
        generateLOAHTML() {
            const phoneNumbersHTML = this.state.portingPhoneNumbers.map(p => {
                return `<tr><td>${p.phone_number}</td><td>${p.service_provider}</td></tr>`;
            }).join('');

            return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { border: 1px solid #000; padding: 8px; }
        .signature-img { max-width: 300px; height: auto; border: 1px solid #ccc; }
        .authorization { font-size: 11px; line-height: 1.5; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PORTING LETTER OF AUTHORIZATION (LOA)</h1>
    </div>

    <div class="section">
        <div class="section-title">1. Customer Name (as it appears on your telephone bill):</div>
        <p>First Name: ${this.state.paymentInfo.first_name}</p>
        <p>Last Name: ${this.state.paymentInfo.last_name}</p>
        <p>Business Name: ${this.state.loaFormData.business_name || 'N/A'}</p>
    </div>

    <div class="section">
        <div class="section-title">2. Service Address on file with your current carrier:</div>
        <p>${this.state.paymentInfo.shipping_address}</p>
        <p>${this.state.paymentInfo.shipping_city}, ${this.state.paymentInfo.shipping_state} ${this.state.paymentInfo.shipping_zip}</p>
    </div>

    <div class="section">
        <div class="section-title">3. List all Telephone Number(s) to be ported:</div>
        <table>
            <thead>
                <tr>
                    <th>Phone Number</th>
                    <th>Service Provider</th>
                </tr>
            </thead>
            <tbody>
                ${phoneNumbersHTML}
            </tbody>
        </table>
    </div>

    <div class="authorization">
        <p>By signing below, I verify that I am, or represent (for a business), the above-named service customer,
        authorized to change the primary carrier(s) for the telephone number(s) listed, and am at least 18 years of age.
        The name and address I have provided is the name and address on record with my local telephone company
        for each telephone number listed. I authorize <strong>Customer2.AI</strong> (the "Company") or its
        designated agent to act on my behalf and notify my current carrier(s) to change my preferred carrier(s) for the
        listed number(s) and service(s), to obtain any information the Company deems necessary to make the carrier
        change(s), including, for example, an inventory of telephone lines billed to the telephone number(s), carrier or
        customer identifying information, billing addresses, and my credit history.</p>
    </div>

    <div class="section">
        <p><strong>Authorized Signature:</strong></p>
        <img src="${this.state.loaFormData.signature}" class="signature-img" alt="Signature">
        <p><strong>Printed Name:</strong> ${this.state.loaFormData.printed_name}</p>
        <p><strong>Date:</strong> ${this.state.loaFormData.date}</p>
    </div>

    <div class="section">
        <p><small>For toll free numbers, please change RespOrg to TWI01. Please do not end service on the number for 10 days after RespOrg change.</small></p>
    </div>
</body>
</html>
            `;
        }


        /**
         * Complete the order
         */
        async completeOrder() {

            this.showLoading('Completing your order...');

            try {
                // Get agent quality pricing if calls were selected
                let agentQualityPricing = null;
                if (this.state.selectedProducts.includes('inbound_outbound_calls') && this.state.agentQuality) {
                    // Capitalize first letter to match pricing key (Quick, Advanced, Conversational)
                    const styleKey = this.state.agentQuality.charAt(0).toUpperCase() + this.state.agentQuality.slice(1);
                    agentQualityPricing = this.pricing.agentQuality[styleKey] || null;
                }

                // Prepare complete order payload
                let orderData = {
                    // Products and addons
                    products: this.state.selectedProducts,
                    addons: this.state.selectedAddons,

                    // Pricing (client-side calculated, will be validated server-side)
                    setup_total: this.pricing.setup,
                    weekly_cost: this.pricing.weekly,
                    total_to_charge: this.pricing.setup,

                    // Sales generated ID for backend pricing validation
                    sales_generated_id: this.state.salesGeneratedId,

                    // Payment info
                    payment: this.state.paymentInfo,

                    // Call setup (if applicable)
                    call_setup: this.state.selectedProducts.includes('inbound_outbound_calls') ? {
                        setup_type: this.state.setupType,
                        number_count: this.state.numberCount,
                        assignment_type: this.state.assignmentType,
                        phone_number_type: this.state.phoneNumberType,
                        agent_quality: this.state.agentQuality,
                        agent_quality_pricing: agentQualityPricing,
                        numbers_to_port: this.state.setupType === 'byo' ? this.state.portingPhoneNumbers : []
                    } : null
                };

                // Log order data without sensitive payment tokens
                const { payment, ...safeOrderData } = orderData;
                console.log('Order data prepared:', JSON.stringify(safeOrderData, null, 2));

                // Send to n8n webhook via ApiProxy
                const response = await this.apiCall('complete_order', orderData);


                if (response.success) {

                    // Clear cached state on successful order completion
                    this.clearState();

                    this.showSuccess();

                    this.saveState();

                    this.renderPortingLOA();

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
            }

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

            // Hide the navigation buttons
            const footer = document.getElementById('aipwModalFooter');
            if (footer) {
                footer.style.display = 'none';
            }
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

            try {
                const response = await fetch(this.config.apiProxy, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                if (!response.ok) {
                    console.error('[apiCall] Response not OK:', response);
                    const errorText = await response.text();
                    console.error('[apiCall] Error response body:', errorText);
                    throw new Error(`API call failed: ${response.status} ${response.statusText}`);
                }

                const jsonResponse = await response.json();

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
                const quickPricing = this.pricing.agentQuality['Quick'] || {};
                const advancedPricing = this.pricing.agentQuality['Advanced'] || {};
                const conversationalPricing = this.pricing.agentQuality['Conversational'] || {};

                return `
                    <div class="aipw-product-pricing">
                        <div class="aipw-pricing-title" style="font-weight: 600; margin-bottom: 6px; font-size: 14px;">Usage-Based Pricing:</div>
                        <div class="aipw-pricing-tier" style="font-size: 12px; margin-bottom: 2px;">
                            Quick: ${this.formatCurrency(quickPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-tier" style="font-size: 12px; margin-bottom: 2px;">
                            Advanced: ${this.formatCurrency(advancedPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-tier" style="font-size: 12px; margin-bottom: 2px;">
                            Conversational: ${this.formatCurrency(conversationalPricing.phone_per_minute || 0)}/min
                        </div>
                        <div class="aipw-pricing-note" style="font-size: 12px; opacity: 0.7; margin-top: 4px; font-style: italic;">
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
                    <div class="aipw-pricing-details" style="font-size: 12px; opacity: 0.8;">
                        <div style="margin-bottom: 2px;">Includes ${pricing.email_threshold} emails/week</div>
                        ${pricing.email_cost_overage ? `<div>Overage: ${this.formatCurrency(pricing.email_cost_overage)}/email</div>` : ''}
                    </div>
                `;
            } else if (productKey === 'chatbot' && pricing.chat_threshold) {
                pricingHTML += `
                    <div class="aipw-pricing-details" style="font-size: 12px; opacity: 0.8;">
                        <div style="margin-bottom: 2px;">Includes ${pricing.chat_threshold} chats/week</div>
                        ${pricing.chat_cost_overage ? `<div>Overage: ${this.formatCurrency(pricing.chat_cost_overage)}/chat</div>` : ''}
                    </div>
                `;
            }

            pricingHTML += `</div>`;

            return pricingHTML;
        }

        /**
         * Get addon pricing HTML for display in product card
         */
        getAddonPricingHTML(addon) {
            const addon_pricing = this.pricing.addons[addon];

            // Default pricing for addons not in database
            let price = 0;
            let unit = '/week';

            // Special case: Phone Numbers uses phoneNumberWeeklyCost
            if (addon === 'Phone Numbers') {
                price = this.pricing.phoneNumberWeeklyCost || 0;
                unit = '/number/week';
            } else if (addon_pricing) {
                price = addon_pricing.weekly || 0;
            } else {
                // Set default $0 pricing for missing addons
                if (addon === 'Lead Verification') {
                    unit = '/lead';
                } else {
                    unit = '/week';
                }
            }

            // Return pricing HTML for addons
            return `
                <div class="aipw-addon-pricing">
                    <div class="aipw-addon-pricing-tier" style="font-size: 14px;">
                        ${this.formatCurrency(price)}${unit}
                    </div>
                </div>
            `;
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

    window.addEventListener('load', function () {
        // Detect if the page was loaded via a hard reload
        const navEntries = performance.getEntriesByType('navigation');
        const navType = navEntries.length > 0 ? navEntries[0].type : performance.navigation.type;

        if (navType === 'reload') {
            // Page was hard-refreshed (Ctrl+R, F5, etc.)
            localStorage.removeItem('aipw_widget_state');
        }
    });

    // Initialize widget when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.aipwWidget = new AIProductsWidget(window.aipwConfig || {});
        });
    } else {
        window.aipwWidget = new AIProductsWidget(window.aipwConfig || {});
    }

})();
