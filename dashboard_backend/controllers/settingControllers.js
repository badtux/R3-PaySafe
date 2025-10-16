const { saveHardcodedGateways, getTenantGateways, updateTenantGateways } = require('../services/setting.service');

async function initializeGateways(req, res) {
  try {
    await saveHardcodedGateways();
    res.json({ success: true, message: "Gateways initialized successfully" });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
}


async function fetchTenantGateways(req, res) {
  try {
    const { tenant } = req.params;
    const data = await getTenantGateways(tenant);

    if (!data) {
      return res.status(404).json({ success: false, message: "Tenant not found" });
    }

    res.json({ success: true, data });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
}


async function activateTenantGateways(req, res) {
  try {
    const { tenant } = req.params;
    const { gateways } = req.body; 

    if (!gateways || typeof gateways !== 'object') {
      return res.status(400).json({ success: false, message: "Invalid gateways object" });
    }

    const result = await updateTenantGateways(tenant, gateways);
    res.json({ success: true, data: result });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
}

module.exports = {
  initializeGateways,
  fetchTenantGateways,
  activateTenantGateways
};
