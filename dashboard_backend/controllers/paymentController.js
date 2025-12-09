
const paymentService = require('../services/payment.service');
const {sendRefundEmail} =require('../services/send.mail')

exports.healthCheck = async (req, res) => {
    try {
        const result = await paymentService.checkHealth(req.hostname);
        res.status(200).json(result);
    } catch (error) {
        console.error('Health check error:', error.message);
        res.status(400).json({ status: 'error', message: error.message });
    }
};

exports.getPayments = async (req, res) => {
    try {
        const result = await paymentService.fetchPayments(req.hostname, req.query);
        res.status(200).json(result);
    } catch (error) {
        console.error('Error fetching payments:', error);
        res.status(500).json({ status: 'error', message: 'Failed to fetch payments' });
    }
};

// refund.controller.js


exports.refundPayment = async (req, res) => {
  try {
    const { email } = req.body;
    console.log("📨 Refund Request Body:", req.body);

    // Process refund via gateway
    const result = await paymentService.refundPayment_services(req.hostname, req.body);

    let emailMessage = null;
    if (result && result.refundId && email) {
      try {
        await sendRefundEmail(email, {
          orderId: result.orderId,
          refundId: result.refundId,
          amount: parseFloat(result.amount || req.body.amount),
          currency: result.currency || req.body.currency,
        });

        emailMessage = `Confirmation email sent successfully`;
        console.log("✅", emailMessage);
      } catch (emailError) {
        emailMessage = `Refund processed, but email sending failed: ${emailError.message}`;
        console.error("❌ Email sending failed:", emailError.message);
      }
    }

    // Response headers
    res.setHeader("Content-Type", "application/json; charset=utf-8");
    res.setHeader("Cache-Control", "no-store, no-cache, must-revalidate, proxy-revalidate");
    res.setHeader("Pragma", "no-cache");
    res.setHeader("Expires", "0");

    // ✅ Send response with email status
    return res.status(200).json({
      success: true,
      ...result,
      message: "Refund processed successfully.",
      emailStatus: emailMessage || "No email address provided.",
    });

  } catch (error) {
    console.error("Refund failed:", {
      message: error.message,
      stack: error.stack,
      gateway: error.gateway || null,
    });

    const gatewayError = error.gateway || error.error || {};
    const explanation = gatewayError.explanation || gatewayError.cause || null;

    res.setHeader("Content-Type", "application/json; charset=utf-8");
    res.setHeader("Cache-Control", "no-store");

    return res.status(400).json({
      success: false,
      message: error.message || "Refund failed",
      error: {
        explanation,
        cause: gatewayError.cause || null,
        field: gatewayError.field || null,
        validationType: gatewayError.validationType || null,
      },
    });
  }
};

