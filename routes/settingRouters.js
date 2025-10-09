// routes/gatewayRoutes.js
const express = require('express');
const router = express.Router();
const { initializeGateways, fetchTenantGateways,activateTenantGateways } = require('../controllers/settingControllers');

router.post('/initialize', initializeGateways); 
router.get('/:tenant/gateways', fetchTenantGateways);    
router.post('/:tenant/gateways/activate', activateTenantGateways);

module.exports = router;
