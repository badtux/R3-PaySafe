const { MongoClient } = require('mongodb');

const LIVE = false; // your current environment
const DATABASE_NAME = 'DT-Plutos';
const TENANT_COLLECTION = 'malkey';

// Use correct Mongo URI depending on LIVE/DEV
const DATABASE_URL = LIVE
  ? 'mongodb://127.0.0.1:27017'
  : 'mongodb+srv://piumal0713:Adyp%400713@cluster0.8bv15.mongodb.net/?retryWrites=true&w=majority';

async function getGatewayCredentials(currency) {
  const client = new MongoClient(DATABASE_URL, { useUnifiedTopology: true });
  await client.connect();

  try {
    const db = client.db(DATABASE_NAME);
    const collection = db.collection(TENANT_COLLECTION);

    const curr = currency.toUpperCase();
    const liveStatus = !!LIVE; // ensure boolean

    console.log('🔹 Searching credentials for:', { currency: curr, live: liveStatus });

    // Find the credential
    const credential = await collection.findOne({
      currency: curr,
      live: liveStatus,
    });

    if (!credential) {
      throw new Error(`No credentials found for currency: ${curr} and live: ${liveStatus}`);
    }

    console.log(`🟡 Gateway mode: ${liveStatus ? 'LIVE' : 'TEST'} | Currency: ${curr}`);
    return {
      merchantId: credential.merchantId,
      apiUsername: credential.apiUserName,
      apiPassword: credential.apiPassWord,
       live: liveStatus,
    };
  } finally {
    await client.close();
  }
}

module.exports = { getGatewayCredentials };
