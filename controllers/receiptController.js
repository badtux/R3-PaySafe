const path = require("path");
const fs = require("fs");
const pdf = require("html-pdf");
const { getPaymentCollection } = require("../config/db");

exports.downloadReceipt = async (req, res) => {
  try {
    const { orderId } = req.params;
    if (!orderId) {
      return res.status(400).json({ message: "Order ID is required" });
    }

    const paymentCollection = getPaymentCollection();
    const payment = await paymentCollection.findOne({ orderId: orderId });

    if (!payment) {
      return res.status(404).json({ message: "Payment not found" });
    }

    // Load receipt.html
    const filePath = path.join(__dirname, "../public/receipt.html");
    let html = fs.readFileSync(filePath, "utf-8");

    // Replace placeholders with real DB values
    const replacements = {
      status: payment.paymentStatus || "N/A",
      orderId: payment.orderId || "N/A",
      currency: payment.currency || "LKR",
      amount: payment.amount ? payment.amount.toFixed(2) : "0.00",
      transactionId: payment.transactionId || "N/A",
      dateTime: new Date(payment.updatedAt || payment.createdAt || Date.now()).toLocaleString(),
      sourceAccount: payment.cardNumber || "************",
      beneficiaryAccount: payment.beneficiaryAccount || "N/A",
      remarks: payment.description || "N/A",
      bank: payment.bank || "N/A",
      bankLogo: "https://www.seylan.lk/images/web/icons/logo-2025.png"
    };

    Object.keys(replacements).forEach((key) => {
      html = html.replace(`{{${key}}}`, replacements[key]);
    });

    // Generate PDF
    pdf.create(html, { format: "A4" }).toStream((err, stream) => {
      if (err) {
        console.error("PDF Error:", err);
        return res.status(500).send("PDF generation failed");
      }
      res.setHeader("Content-Disposition", `attachment; filename=receipt-${orderId}.pdf`);
      res.setHeader("Content-Type", "application/pdf");
      stream.pipe(res);
    });

  } catch (err) {
    console.error("Receipt PDF Error:", err);
    res.status(500).json({ message: "Error generating PDF" });
  }
};
