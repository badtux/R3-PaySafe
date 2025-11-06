const { getGatewayCredentials } = require('./refund.setting.services');
const axios = require('axios');
const https = require('https');

class RefundService {
  async processRefund(orderId, refundAmount, currency) {
    // Await the credentials
   const { merchantId, apiUsername, apiPassword, live } = await getGatewayCredentials(currency);
    const domain = 'cbcmpgs.gateway.mastercard.com';
    const apiVersion = '57';
    const refundTxId = `refund_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
    const url = `https://${domain}/api/rest/version/${apiVersion}/merchant/${merchantId}/order/${orderId}/transaction/${refundTxId}`;

    const payload = {
      apiOperation: 'REFUND',
      transaction: {
        amount: refundAmount.toFixed(2),
        currency: currency.toUpperCase(),
      },
    };

    const auth = Buffer.from(`${apiUsername}:${apiPassword}`).toString('base64');
    console.log(`🔹 Refund Request URL: ${url}`);
    console.log(`🔹 Using Merchant: ${merchantId}`);
    console.log(`🔹 Auth User: ${apiUsername}`);

    try {
      const response = await axios.put(url, payload, {
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Basic ${auth}`,
        },
        timeout: 30000,
        httpsAgent: new https.Agent({ rejectUnauthorized: true }),
      });


     const data = response.data;
     console.log (data);
       return {
        success: data.result === 'SUCCESS',
        gatewayCode: data.response?.gatewayCode || null,
        result: data.result || null,
        transactionId: data.transaction?.id || refundTxId,
        httpCode: response.status,
        order: data.order || null,
        raw: data,
      };
    } catch (error) {
      const errData = error.response?.data;
      const cause = errData?.error?.cause || 'UNKNOWN';
      const explanation = errData?.error?.explanation || error.message;

      console.error('❌ Refund API Error:', errData);

      return {
        success: false,
        httpCode: error.response?.status || 500,
        error: {
          cause,
          explanation,
          field: errData?.error?.field || null,
          supportCode: errData?.error?.supportCode || null,
          validationType: errData?.error?.validationType || null,
        },
        raw: errData || null,
      };
    }
  }
}

module.exports = new RefundService();
