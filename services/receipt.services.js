
const { getPaymentCollection } = require('../config/db');
const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');


function validateTenantAndOrderId(tenant, orderId) {
    if (!tenant || tenant === 'localhost' || tenant === 'go' || !/^[a-zA-Z0-9-]+$/.test(tenant)) {
        throw new Error('Invalid tenant. Use a valid subdomain (e.g., malkey.localhost:3008)');
    }
    if (!orderId || !/^[a-zA-Z0-9]+$/.test(orderId)) {
        throw new Error('Invalid orderId format. Use alphanumeric characters only.');
    }
}


function buildReplacements(payment, tenant) {
    const bankLogo = `file://${path.join(__dirname, '..', 'public', 'images', 'default-bank-logo.png')}`;
    const bankFooter = `© ${new Date().getFullYear()} ${payment.bank}. All rights reserved.`;
    const bankHeader = `${payment.bank} - Online Transfer`;

    return {
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
        bank: payment.bank,
        bankLogo,
        bankFooter,
        bankHeader,
        headerColor: 'text-blue-600'
    };
}


function replacePlaceholders(html, replacements) {
    Object.keys(replacements).forEach(key => {
        const placeholder = `{{${key}}}`;
        const value = replacements[key] || '';
        const escapedValue = String(value).replace(/[<>&"]/g, match => ({
            '<': '&lt;',
            '>': '&gt;',
            '&': '&amp;',
            '"': '&quot;'
        }[match]));
        html = html.replace(new RegExp(placeholder, 'g'), escapedValue);
    });
    return html;
}


async function generateReceiptPDF(tenant, orderId) {
    validateTenantAndOrderId(tenant, orderId);

    const paymentCollection = await getPaymentCollection(tenant);
    const payment = await paymentCollection.findOne({ orderId });
    if (!payment) throw new Error(`Payment not found for orderId: ${orderId}`);

    const replacements = buildReplacements(payment, tenant);


    const templatePath = path.join(__dirname, '..', 'views', 'receipt.html');
    let htmlContent = await fs.readFile(templatePath, 'utf-8');
    htmlContent = replacePlaceholders(htmlContent, replacements);

 
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
        timeout: 90000
    });
    const page = await browser.newPage();

  
    await page.setRequestInterception(true);
    page.on('request', request => {
        const url = request.url();
        if (url.includes('cdn.tailwindcss.com') || url.startsWith('http')) {
            request.abort();
        } else {
            request.continue();
        }
    });

    await page.setContent(htmlContent, { waitUntil: 'load', timeout: 90000 });
    const pdfBuffer = await page.pdf({
        width: '168mm',
        height: '168mm',
        printBackground: true,
        margin: { top: '20px', right: '20px', bottom: '20px', left: '20px' }
    });

    await browser.close();

    if (!pdfBuffer || pdfBuffer.length === 0) {
        throw new Error('Generated PDF is empty or invalid');
    }

    return pdfBuffer;
}

module.exports = {
    generateReceiptPDF
};
