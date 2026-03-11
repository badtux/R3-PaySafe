const cron = require('node-cron');
const axios = require('axios');
const { getPaymentCollection } = require('../../models/Payment');

async function eCardJob() {
  try {
    const paymentCollection = await getPaymentCollection('helpage');
    const pendingECards = await paymentCollection.find({
      eCardEmail: false
    }).toArray();
    
    if (pendingECards.length === 0) {
      return;
    }
    
    console.log(`[eCard Cron] Found ${pendingECards.length} transactions needing eCard execution.`);
    
    for (const payment of pendingECards) {
      try {
        const payload = {
          orderId: payment.orderId,
          transactionId: payment.transactionId,
          amount: payment.amount,
          currency: payment.currency,
          description: payment.description,
          bank: payment.bank,
          cardBrand: payment.cardBrand,
          cardNumber: payment.cardNumber,
          email: payment.email,
          nameOnCard: payment.nameOnCard,
          paymentStatus: payment.paymentStatus || 'PENDING',
          address: payment.address || "",
        };
        
        let targetUrl;
        let isSuccess = false;

        if (payment.paymentStatus === 'SUCCESS') {
          targetUrl = payment.successUrl;
          isSuccess = true;
        } else {
          targetUrl = payment.failedUrl;
          isSuccess = false;
        }

        if (!targetUrl) {
            console.log(`[eCard Cron] No targetUrl found for uuid ${payment.uuid}. Skipping.`);
            continue;
        }
        const failCount = payment.eCardFailCount || 0;
        if (!isSuccess && failCount >= 2) {
            continue;
        }

        console.log(`[eCard Cron] Sending to ${targetUrl} for uuid: ${payment.uuid}, isSuccess: ${isSuccess}`);
        const response = await axios.post(targetUrl, payload, {
           headers: {
             'Content-Type': 'application/json'
           },
           timeout: 10000 
        });
        
        if (response.status >= 200 && response.status < 300) {
           if (isSuccess) {
             console.log(`[eCard Cron] Successfully hit successUrl for uuid ${payment.uuid}. Marking eCardEmail=true`);
             await paymentCollection.updateOne(
               { uuid: payment.uuid },
               { $set: { eCardEmail: true, eCardSentAt: new Date() } }
             );
           } else {
             console.log(`[eCard Cron] Successfully hit failedUrl for uuid ${payment.uuid}. Incrementing eCardFailCount.`);
             await paymentCollection.updateOne(
               { uuid: payment.uuid },
               { 
                 $set: { eCardFailedSentAt: new Date() },
                 $inc: { eCardFailCount: 1 } 
               }
             );
           }
        } else {
           console.error(`[eCard Cron] Unexpected response status ${response.status} for uuid ${payment.uuid}`);
        }
      } catch (err) {
        console.error(`[eCard Cron] Failed to send eCard hook for uuid ${payment.uuid}:`, err.message);
      }
    }
    
  } catch (error) {
    console.error('[eCard Cron] Error running eCardJob:', error);
  }
}

function startECardCron() {
  console.log('[eCard Cron] Initialized. Running every 5 minute...');
  cron.schedule('*/10 * * * *', () => {
    eCardJob();
  });
}

module.exports = {
  startECardCron,
  eCardJob
};
