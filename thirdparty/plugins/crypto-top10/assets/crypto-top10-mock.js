/**
 * Mock data for Crypto Top 10 plugin testing
 * This provides sample data when the CoinGecko API is unavailable
 */

(function() {
    'use strict';
    
    // Mock top 10 cryptocurrencies data
    window.MOCK_CRYPTO_LIST = [
        { id: 'bitcoin', name: 'Bitcoin', symbol: 'BTC' },
        { id: 'ethereum', name: 'Ethereum', symbol: 'ETH' },
        { id: 'tether', name: 'Tether', symbol: 'USDT' },
        { id: 'binancecoin', name: 'BNB', symbol: 'BNB' },
        { id: 'solana', name: 'Solana', symbol: 'SOL' },
        { id: 'usd-coin', name: 'USDC', symbol: 'USDC' },
        { id: 'ripple', name: 'XRP', symbol: 'XRP' },
        { id: 'cardano', name: 'Cardano', symbol: 'ADA' },
        { id: 'avalanche-2', name: 'Avalanche', symbol: 'AVAX' },
        { id: 'dogecoin', name: 'Dogecoin', symbol: 'DOGE' }
    ];
    
    // Generate mock 7-day price data
    function generateMockPriceData(basePrice, volatility) {
        const prices = [];
        const now = Date.now();
        const dayMs = 24 * 60 * 60 * 1000;
        
        for (let i = 6; i >= 0; i--) {
            const timestamp = now - (i * dayMs);
            // Generate price with some random variation
            const variation = (Math.random() - 0.5) * volatility;
            const price = basePrice * (1 + variation);
            prices.push([timestamp, price]);
        }
        
        return prices;
    }
    
    // Mock price data for each cryptocurrency
    window.MOCK_PRICE_DATA = {
        'bitcoin': generateMockPriceData(42000, 0.05),
        'ethereum': generateMockPriceData(2200, 0.08),
        'tether': generateMockPriceData(1.0, 0.001),
        'binancecoin': generateMockPriceData(305, 0.06),
        'solana': generateMockPriceData(98, 0.10),
        'usd-coin': generateMockPriceData(1.0, 0.001),
        'ripple': generateMockPriceData(0.52, 0.07),
        'cardano': generateMockPriceData(0.48, 0.09),
        'avalanche-2': generateMockPriceData(37, 0.11),
        'dogecoin': generateMockPriceData(0.085, 0.12)
    };
    
})();
