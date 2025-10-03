const { getPaymentCollection } = require('../config/db');
const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');

const downloadReceipt = async (req, res) => {
    let browser = null;
    try {
        const { db, orderId } = req.params;

        // Validate db parameter
        if (!['malkey', 'helpage'].includes(db)) {
            console.error(`Invalid db parameter: ${db}`);
            return res.status(400).json({ 
                message: 'Invalid db parameter. Must be "malkey" for Malkey Bank or "helpage" for helpage Bank.' 
            });
        }

        // Validate orderId format
        if (!/^[a-zA-Z0-9]+$/.test(orderId)) {
            console.error(`Invalid orderId format: ${orderId}`);
            return res.status(400).json({ message: 'Invalid orderId format. Use alphanumeric characters only.' });
        }

        // Map db parameter to bank name, logo, footer, header, and header color
        const bankName = db === 'malkey' ? 'Commercial Bank' : 'Seylan Bank';
              const bankLogo = db === 'malkey' 
            ? `file://${path.join(__dirname, '..', 'public', 'images', 'commercial.svg')}`
            : `file://${path.join(__dirname, '..', 'public', 'images', 'seylan.png')}`;
        const bankFooter = `© ${new Date().getFullYear()} ${bankName}. All rights reserved.`;
        const bankHeader = `${bankName} - Online Transfer`;
        const headerColor = db === 'malkey' ? 'text-blue-600' : 'text-red-600';

        // Get the appropriate payment collection
        const paymentCollection = getPaymentCollection(db);

        // Find payment by orderId
        const payment = await paymentCollection.findOne({ orderId });

        if (!payment) {
            console.error(`Payment not found for orderId: ${orderId} in database: ${db}`);
            return res.status(404).json({ message: `Payment not found for orderId: ${orderId}` });
        }

        // Validate payment data
        if (typeof payment.orderId !== 'string' || payment.orderId.trim() === '') {
            console.error(`Invalid orderId in payment: ${JSON.stringify(payment)}`);
            return res.status(500).json({ message: 'Invalid payment data: orderId' });
        }
        if (payment.amount != null && (isNaN(payment.amount) || typeof payment.amount !== 'number')) {
            console.error(`Invalid amount in payment: ${JSON.stringify(payment)}`);
            return res.status(500).json({ message: 'Invalid payment data: amount' });
        }

        // Define replacements for HTML template
        const replacements = {
            status: payment.paymentStatus || 'N/A',
            orderId: payment.orderId || 'N/A',
            currency: payment.currency || 'LKR',
            amount: payment.amount != null ? Number(payment.amount).toFixed(2) : '0.00',
            transactionId: payment.transactionId || 'N/A',
            dateTime: payment.updatedAt || payment.createdAt 
                ? new Date(payment.updatedAt || payment.createdAt).toLocaleString('en-US', { timeZone: 'Asia/Colombo' }) 
                : new Date().toLocaleString('en-US', { timeZone: 'Asia/Colombo' }),
            sourceAccount: payment.cardNumber || '************',
            beneficiaryAccount: payment.beneficiaryAccount || 'N/A',
            remarks: payment.description || 'N/A',
            bank: payment.bank || bankName,
            bankLogo: bankLogo,
            bankFooter: bankFooter,
            bankHeader: bankHeader,
            headerColor: headerColor
        };

        console.log(`Replacements for orderId: ${orderId}, db: ${db}`, JSON.stringify(replacements, null, 2));

        // Read the HTML template
        const templatePath = path.join(__dirname, '..', 'public', 'receipt.html');
        let htmlContent;
        try {
            htmlContent = await fs.readFile(templatePath, 'utf-8');
        } catch (error) {
            console.error(`Failed to read recept.html: ${error.message}`);
            return res.status(500).json({ message: `Failed to load template: ${error.message}` });
        }

        // Replace placeholders in the HTML template
        Object.keys(replacements).forEach(key => {
            const placeholder = `{{${key}}}`;
            const value = replacements[key] || '';
            const escapedValue = String(value).replace(/[<>&"]/g, match => ({
                '<': '&lt;',
                '>': '&gt;',
                '&': '&amp;',
                '"': '&quot;'
            })[match]);
            htmlContent = htmlContent.replace(new RegExp(placeholder, 'g'), escapedValue);
        });

        // Launch Puppeteer
        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--font-render-hinting=none'
            ],
            timeout: 90000
        });
        const page = await browser.newPage();

        // Set request interception to handle external resources
        await page.setRequestInterception(true);
        page.on('request', async request => {
            const url = request.url();
            if (['image', 'stylesheet', 'font', 'script'].includes(request.resourceType())) {
                if (url.includes('malkey-logo.png') || url.includes('seylan-logo.png')) {
                    const logoPath = path.join(__dirname, '..', 'public', url.replace(/^.*\/images\//, 'images/'));
                    try {
                        await fs.access(logoPath);
                        request.continue();
                    } catch {
                        console.warn(`Logo file missing: ${logoPath}. Using fallback.`);
                        request.respond({
                            status: 200,
                            contentType: 'image/png',
                            body: await fs.readFile(path.join(__dirname, '..', 'public', 'images', 'malkey-logo.png'))
                        });
                    }
                } else if (url.includes('cdn.tailwindcss.com')) {
                    console.warn(`Aborting Tailwind CDN request: ${url}`);
                    request.abort();
                } else {
                    console.warn(`Aborting external resource: ${url}`);
                    request.abort();
                }
            } else {
                request.continue();
            }
        });

        page.on('requestfailed', request => {
            console.warn(`Failed to load resource: ${request.url()} - ${request.failure().errorText}`);
        });

        page.on('console', msg => {
            console.log(`Page console: ${msg.type()} - ${msg.text()}`);
        });

        // Set CORS headers
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET');

        // Set the HTML content
        try {
            await page.setContent(htmlContent, {
                waitUntil: 'load',
                timeout: 90000
            });
        } catch (error) {
            console.error(`Failed to set HTML content: ${error.message}`);
            return res.status(500).json({ message: 'Failed to render HTML' });
        }

        // Generate PDF
        let pdfBuffer;
        try {
            pdfBuffer = await page.pdf({
                width: '168mm',
                height: '168mm',
                printBackground: true,
                margin: { top: '20px', right: '20px', bottom: '20px', left: '20px' }
            });
        } catch (error) {
            console.error(`Failed to generate PDF: ${error.message}`);
            return res.status(500).json({ message: 'Failed to generate PDF' });
        }

        // Verify PDF buffer
        if (!pdfBuffer || pdfBuffer.length === 0) {
            console.error('Generated PDF buffer is empty or invalid');
            return res.status(500).json({ message: 'Generated PDF is invalid' });
        }

        // Set response headers
        const fileName = `receipt_${orderId}.pdf`;
        res.setHeader('Content-Type', 'application/pdf');
        res.setHeader('Content-Disposition', `attachment; filename="${fileName}"`);
        res.setHeader('Content-Length', pdfBuffer.length);

        // Send the PDF
        res.send(pdfBuffer);
    } catch (error) {
        console.error(`Error generating receipt for orderId: ${orderId}, db: ${db}`, error);
        res.status(500).json({ message: 'Internal server error' });
    } finally {
        if (browser) {
            await browser.close();
        }
    }
};

module.exports = { downloadReceipt };