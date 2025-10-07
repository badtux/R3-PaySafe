// controllers/receipt.controller.js
const receiptService = require('../services/receipt.services');

exports.downloadReceipt = async (req, res) => {
    let tenant, orderId;
    try {
        orderId = req.params.orderId;
        tenant = req.hostname.split('.')[0];

        const pdfBuffer = await receiptService.generateReceiptPDF(tenant, orderId);

        const fileName = `receipt_${orderId}.pdf`;
        res.setHeader('Content-Type', 'application/pdf');
        res.setHeader('Content-Disposition', `attachment; filename="${fileName}"`);
        res.setHeader('Content-Length', pdfBuffer.length);
        res.send(pdfBuffer);

    } catch (error) {
        console.error(`Error generating receipt for orderId: ${orderId}, tenant: ${tenant}:`, error);
        res.status(500).json({ message: error.message || 'Internal server error' });
    }
};
