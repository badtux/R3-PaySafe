const { getActiveGatewayCollection } = require('../config/db');


async function saveHardcodedGateways() {
  const collection = await getActiveGatewayCollection();

const tenants = [
  {
    tenant: "malkey",
    merchantName: "Malkey",
    allGateways: {
      "visa/master": { name: "Commercial Bank", path: "cmb" },
      "amex": { name: "NationTrust Bank", path: "ntb" }
    },
    activeGateways: {},
    contact: {
      "Phone number":"123456",
      "email":"infor@gmail.com"
    }

  },
  {
    tenant: "helpage",
    merchantName: "Helpage",
    allGateways: {
      "visa/master": { name: "Seylan Bank", path: "seylan" },
      // "amex": { name: "NationTrust Bank", path: "ntb" }
    },
    activeGateways: {},
    
      contact: {
      "Phone number":"123456",
      "email":"infor@gmail.com"
    }
  }
];


  for (const t of tenants) {
    await collection.updateOne(
      { tenant: t.tenant },
      { $set: { ...t, updatedAt: new Date() } },
      { upsert: true }
    );
    console.log(`✅ Saved gateways for tenant: ${t.tenant}`);
  }
}


async function getTenantGateways(tenant) {
  const collection = await getActiveGatewayCollection();
  const result = await collection.findOne({ tenant });
  return result;
}


async function updateTenantGateways(tenant, gateways) {
  const collection = await getActiveGatewayCollection();
  const tenantData = await collection.findOne({ tenant });

  if (!tenantData) throw new Error("Tenant not found");

  const newActive = {};
  for (const [key, selected] of Object.entries(gateways)) {
    if (selected && tenantData.allGateways[key]) {
      newActive[key] = {
        name: tenantData.allGateways[key].name,
        path: tenantData.allGateways[key].path
      };
    }
  }


  await collection.updateOne(
    { tenant },
    { $set: { activeGateways: newActive, updatedAt: new Date() } }
  );

  return { ...tenantData, activeGateways: newActive };
}


module.exports = {
  saveHardcodedGateways,
  getTenantGateways,
  updateTenantGateways
};
