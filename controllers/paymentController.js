
const paymentService = require('../services/payment.service');

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

exports.exportPayments = async (req, res) => {
    try {
        const data = await paymentService.exportPayments(req.hostname, req.query);
        res.status(200).json(data);
    } catch (error) {
        console.error('Error exporting payments:', error);
        res.status(500).json({ status: 'error', message: 'Failed to export payments' });
    }
};
