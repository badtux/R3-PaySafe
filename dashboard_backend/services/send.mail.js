const nodemailer = require("nodemailer");
const mailConfig = require("../config/mail.config");

async function sendRefundEmail(toEmail, { orderId, amount, currency, refundId }) {
  if (!toEmail) throw new Error("Email address not provided");

  const transporter = nodemailer.createTransport({
    host: mailConfig.host,
    port: mailConfig.port,
    secure: mailConfig.secure,
    auth: mailConfig.auth,
  });

  const mailOptions = {
    from: `"${mailConfig.from.name}" <${mailConfig.from.address}>`,
    to: toEmail,
    cc: mailConfig.ccList,
    subject: "Refund Confirmation - " + mailConfig.name,
    html: `
      <div style="font-family: Arial, sans-serif;">
        <h2 style="color:#1E90FF;">Refund Processed Successfully</h2>
        <p>Hello,</p>
        <p>Your refund has been processed successfully.</p>
        <table>
          <tr><td><strong>Order ID:</strong></td><td>${orderId}</td></tr>
          <tr><td><strong>Refund ID:</strong></td><td>${refundId}</td></tr>
          <tr><td><strong>Amount:</strong></td><td>${amount} ${currency}</td></tr>
        </table>
        <br>
        <p>Best Regards,<br><strong>${mailConfig.name}</strong></p>
      </div>
    `,
  };

  const info = await transporter.sendMail(mailOptions);

  console.log("✅ Email sent successfully!");
  console.log("📤 From:", mailConfig.from.address);
  console.log("📥 To:", toEmail);
  console.log("📋 CC:", mailConfig.ccList.join(", "));
  console.log("✉️ Message ID:", info.messageId);

  return info;
}

module.exports = { sendRefundEmail };
