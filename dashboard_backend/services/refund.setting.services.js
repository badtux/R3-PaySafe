require('dotenv').config();
const { MongoClient } = require('mongodb');

const LIVE = process.env.LIVE === 'true';
const DATABASE_NAME = 'DT-Plutos';
const TENANT_COLLECTION = 'malkey';

const DATABASE_URL = LIVE
  ? process.env.MONGO_URI_LIVE
  : process.env.MONGO_URI_DEV;

async function getGatewayCredentials(currency) {
  const client = new MongoClient(DATABASE_URL, { useUnifiedTopology: true });
  await client.connect();

  try {
    const db = client.db(DATABASE_NAME);
    const collection = db.collection(TENANT_COLLECTION);

    const curr = currency.toUpperCase();
    const liveStatus = !!LIVE;

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
