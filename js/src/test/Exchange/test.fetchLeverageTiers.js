import assert from 'assert';
import testLeverageTier from './base/test.leverageTier.js';
async function testFetchLeverageTiers(exchange, skippedProperties, symbol) {
    const method = 'fetchLeverageTiers';
    const tiers = await exchange.fetchLeverageTiers(symbol);
    // const format = {
    //     'RAY/USDT': [
    //       {},
    //     ],
    // };
    assert(typeof tiers === 'object', exchange.id + ' ' + method + ' ' + symbol + ' must return an object. ' + exchange.json(tiers));
    const tierKeys = Object.keys(tiers);
    const arrayLength = tierKeys.length;
    assert(arrayLength >= 1, exchange.id + ' ' + method + ' ' + symbol + ' must have at least one entry. ' + exchange.json(tiers));
    for (let i = 0; i < arrayLength; i++) {
        const tiersForSymbol = tiers[tierKeys[i]];
        const arrayLengthSymbol = tiersForSymbol.length;
        assert(arrayLengthSymbol >= 1, exchange.id + ' ' + method + ' ' + symbol + ' must have at least one entry. ' + exchange.json(tiers));
        for (let j = 0; j < tiersForSymbol.length; j++) {
            testLeverageTier(exchange, skippedProperties, method, tiersForSymbol[j]);
        }
    }
}
export default testFetchLeverageTiers;
