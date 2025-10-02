const PDFDocument = require('pdfkit');
const { getPaymentCollection } = require('../config/db');

exports.generatePDF = async (req, res) => {
  try {
    const paymentId = req.params.paymentId;
    if (!paymentId) {
      return res.status(400).json({ message: 'Payment ID is required' });
      
    }

    const paymentCollection = getPaymentCollection();

    // Fetch payment data
    const payment = await paymentCollection.findOne({ _id: paymentId });
    if (!payment) {
      return res.status(404).json({ message: 'Payment not found' });
    }

    const doc = new PDFDocument({
      size: 'A4',
      margin: 50,
    });
    const fileName = `payment-receipt-${paymentId}.pdf`;

    // Set headers for PDF download
    res.setHeader('Content-Disposition', `attachment; filename="${fileName}"`);
    res.setHeader('Content-Type', 'application/pdf');

    // Pipe the PDF document to the response
    doc.pipe(res);

    // Header
    doc
      .fillColor('#003087')
      .fontSize(24)
      .font('Helvetica-Bold')
      .text('Malkey Paysafe', 50, 50, { align: 'center' });
    doc
      .fontSize(16)
      .text('Payment Receipt', 50, 80, { align: 'center' });
    doc
      .moveTo(50, 100)
      .lineTo(550, 100)
      .strokeColor('#003087')
      .stroke();

    // Payment Details Section
    doc
      .fillColor('black')
      .font('Helvetica')
      .fontSize(12)
      .text(`Receipt ID: ${paymentId}`, 50, 120);
    doc.text(`Date: ${new Date(payment.date || Date.now()).toLocaleDateString()}`, 50, 140);
    doc.text(`Amount: $${(payment.amount || 0).toFixed(2)}`, 50, 160);
    doc.text(`Status: ${payment.status || 'Completed'}`, 50, 180);

    // Table-like layout for details
    doc
      .moveTo(50, 210)
      .lineTo(550, 210)
      .strokeColor('#cccccc')
      .stroke();
    doc
      .fontSize(14)
      .fillColor('#003087')
      .text('Payment Summary', 50, 220);
    doc
      .fontSize(12)
      .fillColor('black')
      .text('Description:', 50, 240);
    doc.text('Payment for services rendered', 150, 240);
    doc.text('Transaction Date:', 50, 260);
    doc.text(new Date(payment.date || Date.now()).toLocaleString(), 150, 260);
    doc.text('Amount Paid:', 50, 280);
    doc.text(`$${(payment.amount || 0).toFixed(2)}`, 150, 280);
    doc
      .moveTo(50, 300)
      .lineTo(550, 300)
      .stroke();

    // Footer
    doc
      .fontSize(10)
      .fillColor('#666666')
      .text('Thank you for your payment!', 50, 700, { align: 'center' });
    doc.text(`Generated on ${new Date().toLocaleString()}`, 50, 720, { align: 'center' });
    doc.text('Malkey Paysafe - All rights reserved', 50, 740, { align: 'center' });

    // Finalize the PDF
    doc.end();
  } catch (error) {
    console.error('Error generating PDF:', error);
    res.status(500).json({ message: 'Error generating PDF' });
  }
};