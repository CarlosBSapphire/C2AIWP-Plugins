/**
 * Standalone Porting LOA Widget
 * Handles fetching and signing of pending porting LOAs
 */

class PortingLoaWidget {
    constructor(config) {
        this.config = config;
        this.currentLoa = null;
        this.signaturePad = null;
        this.utilityBillData = null;
        this.uuid = null;

        this.init();
    }

    /**
     * Initialize the widget
     */
    async init() {
        console.log('[PortingLoaWidget] Initializing...');

        // Get UUID from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        this.uuid = urlParams.get('uuid');

        if (!this.uuid) {
            this.showError('No LOA UUID provided. Please use the link from your email or account page.');
            return;
        }

        console.log('[PortingLoaWidget] UUID from URL:', this.uuid);

        // Fetch the specific LOA by UUID
        await this.fetchLoaByUuid(this.uuid);
    }

    /**
     * Fetch specific LOA by UUID
     */
    async fetchLoaByUuid(uuid) {
        try {
            const response = await this.apiCall('get_loa_by_uuid', {
                uuid: uuid
            });

            if (response.success && response.data) {
                this.currentLoa = response.data;
                console.log('[fetchLoaByUuid] LOA found:', this.currentLoa);

                // Check if already signed
                if (this.currentLoa.signed) {
                    this.showAlreadySigned();
                } else {
                    // Show the form directly
                    this.showLoaForm();
                }
            } else {
                console.error('[fetchLoaByUuid] Error:', response.message);
                this.showError('LOA not found or already signed: ' + (response.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('[fetchLoaByUuid] Exception:', error);
            this.showError('Failed to load LOA. Please try again or contact support.');
        }
    }

    /**
     * Show already signed message
     */
    showAlreadySigned() {
        const loading = document.getElementById('aipwLoaLoading');
        const empty = document.getElementById('aipwLoaEmpty');

        loading.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `
            <h2>LOA Already Signed</h2>
            <p>This Letter of Authorization has already been signed and submitted.</p>
            <p>If you believe this is an error, please contact support.</p>
        `;
    }

    /**
     * Show the LOA signing form
     */
    showLoaForm() {
        // Hide loading, show form
        document.getElementById('aipwLoaLoading').style.display = 'none';
        document.getElementById('aipwLoaForm').style.display = 'block';

        // Parse phone numbers
        let phoneNumbers = [];
        try {
            phoneNumbers = JSON.parse(this.currentLoa.phone_numbers_and_providers);
        } catch (e) {
            console.error('Failed to parse phone numbers:', e);
        }

        // Generate phone number input fields
        const phoneFields = phoneNumbers.map((phone, index) => `
            <div class="aipw-phone-number-row">
                <div class="aipw-form-group">
                    <label class="aipw-form-label">Phone Number ${index + 1}*</label>
                    <input type="tel" class="aipw-form-input"
                           value="${phone.phone_number || ''}"
                           readonly>
                </div>
                <div class="aipw-form-group">
                    <label class="aipw-form-label">Service Provider*</label>
                    <input type="text" class="aipw-form-input"
                           value="${phone.service_provider || ''}"
                           readonly>
                </div>
            </div>
        `).join('');

        // Render the form
        const formHtml = `
            <div class="aipw-loa-form-header">
                <h2>Porting Letter of Authorization</h2>
                <p class="aipw-loa-subtitle">Please complete and sign the form below</p>
                <p class="aipw-loa-uuid">Request ID: ${this.currentLoa.uuid}</p>
            </div>

            <form id="aipwLoaSigningForm">
                <div class="aipw-form-section">
                    <div class="aipw-form-section-title">1. Customer Name</div>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">First Name*</label>
                    <input type="text" class="aipw-form-input" name="first_name" required>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">Last Name*</label>
                    <input type="text" class="aipw-form-input" name="last_name" required>
                </div>

                <div class="aipw-form-group full-width">
                    <label class="aipw-form-label">Business Name (if applicable)</label>
                    <input type="text" class="aipw-form-input" name="business_name">
                </div>

                <div class="aipw-form-section">
                    <div class="aipw-form-section-title">2. Service Address</div>
                </div>

                <div class="aipw-form-group full-width">
                    <label class="aipw-form-label">Address*</label>
                    <input type="text" class="aipw-form-input" name="address" required>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">City*</label>
                    <input type="text" class="aipw-form-input" name="city" required>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">State*</label>
                    <input type="text" class="aipw-form-input" name="state" required>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">ZIP Code*</label>
                    <input type="text" class="aipw-form-input" name="zip" required>
                </div>

                <div class="aipw-form-section">
                    <div class="aipw-form-section-title">3. Phone Numbers to Port</div>
                </div>

                ${phoneFields}

                <div class="aipw-form-section">
                    <div class="aipw-form-section-title">4. Utility Bill Upload</div>
                    <p class="aipw-form-help-text">Please upload a recent utility bill showing your service address</p>
                </div>

                <div class="aipw-form-group full-width">
                    <input type="file" id="aipwUtilityBillUpload" accept=".pdf,.jpg,.jpeg,.png">
                </div>

                <div class="aipw-form-section">
                    <div class="aipw-form-section-title">5. Authorization & Signature</div>
                </div>

                <div class="aipw-authorization-text">
                    <p>By signing below, I verify that I am, or represent (for a business), the above-named service customer, authorized to change the primary carrier(s) for the telephone number(s) listed, and am at least 18 years of age. The name and address I have provided is the name and address on record with my local telephone company for each telephone number listed. I authorize <strong>Customer2.AI</strong> (the "Company") or its designated agent to act on my behalf and notify my current carrier(s) to change my preferred carrier(s) for the listed number(s) and service(s), to obtain any information the Company deems necessary to make the carrier change(s), including, for example, an inventory of telephone lines billed to the telephone number(s), carrier or customer identifying information, billing addresses, and my credit history.</p>
                </div>

                <div class="aipw-signature-section">
                    <label class="aipw-form-label">Signature*</label>
                    <canvas id="aipwSignaturePad" width="600" height="200"></canvas>
                    <button type="button" id="aipwClearSignature" class="aipw-btn aipw-btn-secondary" style="margin-top: 10px;">Clear Signature</button>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">Printed Name*</label>
                    <input type="text" class="aipw-form-input" name="printed_name" required>
                </div>

                <div class="aipw-form-group">
                    <label class="aipw-form-label">Date*</label>
                    <input type="date" class="aipw-form-input" name="date" required value="${new Date().toISOString().split('T')[0]}">
                </div>

                <div class="aipw-form-actions">
                    <button type="submit" class="aipw-btn aipw-btn-primary">Submit Signed LOA</button>
                </div>
            </form>
        `;

        document.getElementById('aipwLoaForm').innerHTML = formHtml;

        // Initialize signature pad
        this.initializeSignaturePad();

        // Add event listeners
        document.getElementById('aipwLoaSigningForm').addEventListener('submit', (e) => this.submitLoa(e));
        document.getElementById('aipwUtilityBillUpload').addEventListener('change', (e) => this.handleFileUpload(e));
    }

    /**
     * Initialize signature pad
     */
    initializeSignaturePad() {
        const canvas = document.getElementById('aipwSignaturePad');
        const ctx = canvas.getContext('2d');

        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;

        canvas.addEventListener('mousedown', (e) => {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            lastX = e.clientX - rect.left;
            lastY = e.clientY - rect.top;
        });

        canvas.addEventListener('mousemove', (e) => {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            const currentX = e.clientX - rect.left;
            const currentY = e.clientY - rect.top;

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(currentX, currentY);
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();

            lastX = currentX;
            lastY = currentY;
        });

        canvas.addEventListener('mouseup', () => isDrawing = false);
        canvas.addEventListener('mouseout', () => isDrawing = false);

        // Touch support for mobile
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
        });

        canvas.addEventListener('touchmove', (e) => {
            if (!isDrawing) return;
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            const currentX = touch.clientX - rect.left;
            const currentY = touch.clientY - rect.top;

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(currentX, currentY);
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();

            lastX = currentX;
            lastY = currentY;
        });

        canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            isDrawing = false;
        });

        // Clear signature button
        document.getElementById('aipwClearSignature').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
    }

    /**
     * Handle utility bill file upload
     */
    handleFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            this.utilityBillData = {
                base64: event.target.result.split(',')[1],
                filename: file.name,
                mime_type: file.type,
                extension: file.name.split('.').pop()
            };
            console.log('[handleFileUpload] File uploaded:', file.name);
        };
        reader.readAsDataURL(file);
    }

    /**
     * Submit the signed LOA
     */
    async submitLoa(e) {
        e.preventDefault();

        const form = e.target;
        const canvas = document.getElementById('aipwSignaturePad');

        // Validate signature
        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const hasSignature = imageData.data.some(channel => channel !== 0);

        if (!hasSignature) {
            alert('Please provide your signature');
            return;
        }

        try {
            // Get form data
            const formData = new FormData(form);
            const signatureBase64 = canvas.toDataURL('image/png');

            // Generate LOA HTML
            const loaHtml = this.generateLoaHtml(formData, signatureBase64);

            // Submit to server
            const response = await this.apiCall('update_loa_signature', {
                uuid: this.currentLoa.uuid,
                loa_html: btoa(loaHtml),
                utility_bill_base64: this.utilityBillData?.base64 || null,
                utility_bill_filename: this.utilityBillData?.filename || null,
                utility_bill_mime_type: this.utilityBillData?.mime_type || null,
                utility_bill_extension: this.utilityBillData?.extension || null
            });

            if (response.success) {
                console.log('[submitLoa] LOA submitted successfully');
                this.showSuccess();
            } else {
                throw new Error(response.message || 'Failed to submit LOA');
            }
        } catch (error) {
            console.error('[submitLoa] Error:', error);
            alert('Failed to submit LOA: ' + error.message);
        }
    }

    /**
     * Generate LOA HTML for PDF
     */
    generateLoaHtml(formData, signature) {
        // Parse phone numbers
        let phoneNumbers = [];
        try {
            phoneNumbers = JSON.parse(this.currentLoa.phone_numbers_and_providers);
        } catch (e) {
            console.error('Failed to parse phone numbers:', e);
        }

        const phoneRows = phoneNumbers.map(p =>
            `<tr><td>${p.phone_number}</td><td>${p.service_provider}</td></tr>`
        ).join('');

        return `
<!DOCTYPE html>
<html>
<head>
    <title>Letter of Authorization</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f5f5f5; }
        .signature-img { max-width: 300px; border: 1px solid #ddd; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Letter of Authorization</h1>
        <p>For Local Number Portability</p>
        <p><strong>Request ID:</strong> ${this.currentLoa.uuid}</p>
    </div>

    <div class="section">
        <h3>Customer Information</h3>
        <p><strong>Name:</strong> ${formData.get('first_name')} ${formData.get('last_name')}</p>
        ${formData.get('business_name') ? `<p><strong>Business:</strong> ${formData.get('business_name')}</p>` : ''}
        <p><strong>Address:</strong> ${formData.get('address')}, ${formData.get('city')}, ${formData.get('state')} ${formData.get('zip')}</p>
    </div>

    <div class="section">
        <h3>Phone Numbers to Port</h3>
        <table>
            <tr><th>Phone Number</th><th>Service Provider</th></tr>
            ${phoneRows}
        </table>
    </div>

    <div class="section">
        <h3>Authorization</h3>
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
        <p><strong>Signature:</strong></p>
        <img src="${signature}" class="signature-img" alt="Signature">
        <p><strong>Printed Name:</strong> ${formData.get('printed_name')}</p>
        <p><strong>Date:</strong> ${formData.get('date')}</p>
    </div>

    <div class="section">
        <p><small>For toll free numbers, please change RespOrg to TWI01. Please do not end service on the number for 10 days after RespOrg change.</small></p>
    </div>
</body>
</html>
        `;
    }

    /**
     * Show success message
     */
    showSuccess() {
        document.getElementById('aipwLoaForm').style.display = 'none';
        document.getElementById('aipwLoaSuccess').style.display = 'block';
    }

    /**
     * Show error message
     */
    showError(message) {
        const loading = document.getElementById('aipwLoaLoading');
        const empty = document.getElementById('aipwLoaEmpty');

        loading.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `
            <h2>Error</h2>
            <p>${message}</p>
        `;
    }

    /**
     * API call helper
     */
    async apiCall(action, data) {
        const requestBody = {
            action: 'aipw_' + action,
            nonce: this.config.nonce,
            data: data
        };

        console.log('[apiCall] Request:', action, data);

        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const jsonResponse = await response.json();
            console.log('[apiCall] Response:', jsonResponse);

            return jsonResponse;
        } catch (error) {
            console.error('[apiCall] Exception:', error);
            throw error;
        }
    }
}

// Initialize widget when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof aipwLoaConfig !== 'undefined') {
        new PortingLoaWidget(aipwLoaConfig);
    }
});
