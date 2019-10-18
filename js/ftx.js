'use strict';

//  ---------------------------------------------------------------------------

const Exchange = require ('./base/Exchange');
const { ExchangeError, InvalidOrder, BadRequest, InsufficientFunds, OrderNotFound } = require ('./base/errors');

//  ---------------------------------------------------------------------------

module.exports = class ftx extends Exchange {
    describe () {
        return this.deepExtend (super.describe (), {
            'id': 'ftx',
            'name': 'FTX',
            'countries': [ 'HK' ],
            'rateLimit': 100,
            'certified': false,
            'urls': {
                'logo': 'https://theme.zdassets.com/theme_assets/9179536/d48e830d666da07d0a1fcdb74e5ed665d4d4a069.png',
                'www': 'https://ftx.com',
                'api': 'https://ftx.com/api',
                'doc': 'https://github.com/ftexchange/ftx',
            },
            'version': 'v1',
            'has': {
                'fetchDepositAddress': true,
                'fetchTickers': true,
                'fetchTicker': true,
                'fetchOHLCV': true,
                'fetchCurrencies': true,
                'fetchOrder': true,
                'fetchOrders': true,
                'fetchOpenOrders': true,
                'fetchClosedOrders': false,
                'fetchMyTrades': true,
                'withdraw': true,
                'fetchFundingFees': false,
                'fetchDeposits': true,
                'fetchWithdrawals': true,
                'fetchTrades': true,
                'fetchOrderBook': true,
                'cancelAllOrders': true,
            },
            'timeframes': {
                '15s': '15',
                '1m': '60',
                '5m': '300',
                '15m': '900',
                '1h': '3600',
                '4h': '14400',
                '24h': '86400',
            },
            'api': {
                'public': {
                    'get': [
                        'coins',
                        'markets',
                        'markets/{market_name}',
                        'markets/{market_name}/orderbook', // ?depth={depth}
                        'markets/{market_name}/trades', // ?limit={limit}&start_time={start_time}&end_time={end_time}
                        'markets/{market_name}/candles', // ?resolution={resolution}&limit={limit}&start_time={start_time}&end_time={end_time}
                        'futures',
                        'futures/{future_name}',
                        'futures/{future_name}/stats',
                        'funding_rates',
                        'lt/tokens',
                        'lt/{token_name}',
                    ],
                },
                'private': {
                    'get': [
                        'account',
                        'positions',
                        'wallet/coins',
                        'wallet/balances',
                        'wallet/deposit_address/{coin}',
                        'wallet/deposits',
                        'wallet/withdrawals',
                        'orders', // ?market={market}
                        'orders/history', // ?market={market}
                        'orders/{order_id}',
                        'orders/by_client_id/{client_order_id}',
                        'fills', // ?market={market}
                        'funding_payments',
                        'lt/balances',
                        'lt/creations',
                        'lt/redemptions',
                    ],
                    'post': [
                        'account/leverage',
                        'wallet/withdrawals',
                        'orders',
                        'conditional_orders',
                        'lt/{token_name}/create',
                        'lt/{token_name}/redeem',
                    ],
                    'delete': [
                        'orders/{order_id}',
                        'orders/by_client_id/{client_order_id}',
                        'orders',
                    ],
                },
            },
            'exceptions': {
                'exact': {
                    'Not enough balances': InsufficientFunds, // {"error":"Not enough balances","success":false}
                },
                'broad': {
                    'Invalid parameter': BadRequest, // {"error":"Invalid parameter start_time","success":false}
                    'The requested URL was not found on the server': OrderNotFound,
                },
            },
        });
    }

    async fetchCurrencies (params = {}) {
        const response = await this.publicGetCoins (params);
        const currencies = this.safeValue (response, 'result', []);
        //
        //     {
        //         "success":true,
        //         "result": [
        //             {"id":"BTC","name":"Bitcoin"},
        //             {"id":"ETH","name":"Ethereum"},
        //             {"id":"ETHMOON","name":"10X Long Ethereum Token","underlying":"ETH"},
        //             {"id":"EOSBULL","name":"3X Long EOS Token","underlying":"EOS"},
        //         ],
        //     }
        //
        const result = {};
        for (let i = 0; i < currencies.length; i++) {
            const currency = currencies[i];
            const id = this.safeString (currency, 'id');
            const code = this.safeCurrencyCode (id);
            const name = this.safeString (currency, 'name');
            result[code] = {
                'id': id,
                'code': code,
                'info': currency,
                'type': undefined,
                'name': name,
                'active': undefined,
                'fee': undefined,
                'precision': undefined,
                'limits': {
                    'amount': {
                        'min': undefined,
                        'max': undefined,
                    },
                    'price': {
                        'min': undefined,
                        'max': undefined,
                    },
                    'cost': {
                        'min': undefined,
                        'max': undefined,
                    },
                    'withdraw': {
                        'min': undefined,
                        'max': undefined,
                    },
                },
            };
        }
        return result;
    }

    async fetchMarkets (params = {}) {
        const response = await this.publicGetMarkets (params);
        //
        //     {
        //         'success': true,
        //         "result": [
        //             {
        //                 "ask":170.37,
        //                 "baseCurrency":null,
        //                 "bid":170.31,
        //                 "change1h":-0.019001554672655036,
        //                 "change24h":-0.024841165359738997,
        //                 "changeBod":-0.03816406029469881,
        //                 "enabled":true,
        //                 "last":170.37,
        //                 "name":"ETH-PERP",
        //                 "price":170.37,
        //                 "priceIncrement":0.01,
        //                 "quoteCurrency":null,
        //                 "quoteVolume24h":7742164.59889,
        //                 "sizeIncrement":0.001,
        //                 "type":"future",
        //                 "underlying":"ETH",
        //                 "volumeUsd24h":7742164.59889
        //             },
        //             {
        //                 "ask":170.44,
        //                 "baseCurrency":"ETH",
        //                 "bid":170.41,
        //                 "change1h":-0.018485459257126403,
        //                 "change24h":-0.023825887743413515,
        //                 "changeBod":-0.037605872388481086,
        //                 "enabled":true,
        //                 "last":172.72,
        //                 "name":"ETH/USD",
        //                 "price":170.44,
        //                 "priceIncrement":0.01,
        //                 "quoteCurrency":"USD",
        //                 "quoteVolume24h":382802.0252,
        //                 "sizeIncrement":0.001,
        //                 "type":"spot",
        //                 "underlying":null,
        //                 "volumeUsd24h":382802.0252
        //             },
        //         ],
        //     }
        //
        const result = [];
        const markets = this.safeValue (response, 'result', []);
        for (let i = 0; i < markets.length; i++) {
            const market = markets[i];
            const id = this.safeString (market, 'name');
            const baseId = this.safeString2 (market, 'baseCurrency', 'underlying');
            const quoteId = this.safeString (market, 'quoteCurrency', 'USD');
            const type = this.safeString (market, 'type');
            const base = this.safeCurrencyCode (baseId);
            const quote = this.safeCurrencyCode (quoteId);
            // check if a market is a spot or future market
            const symbol = (type === 'future') ? this.safeString (market, 'name') : (base + '/' + quote);
            const active = this.safeValue (market, 'enabled');
            const precision = {
                'amount': this.precisionFromString (this.safeString (market, 'sizeIncrement')),
                'price': this.precisionFromString (this.safeString (market, 'priceIncrement')),
            };
            const entry = {
                'id': id,
                'symbol': symbol,
                'base': base,
                'quote': quote,
                'baseId': baseId,
                'quoteId': quoteId,
                'type': type,
                'future': (type === 'future'),
                'spot': (type === 'spot'),
                'active': active,
                'precision': precision,
                'limits': {
                    'amount': {
                        'min': this.safeFloat (market, 'sizeIncrement'),
                        'max': undefined,
                    },
                    'price': {
                        'min': this.safeFloat (market, 'priceIncrement'),
                        'max': undefined,
                    },
                    'cost': {
                        'min': undefined,
                        'max': undefined,
                    },
                },
                'info': market,
            };
            result.push (entry);
        }
        return result;
    }

    parseTicker (ticker, market = undefined) {
        //
        //     {
        //         "ask":171.29,
        //         "baseCurrency":null, // base currency for spot markets
        //         "bid":171.24,
        //         "change1h":-0.0012244897959183673,
        //         "change24h":-0.031603346901854366,
        //         "changeBod":-0.03297013492914808,
        //         "enabled":true,
        //         "last":171.44,
        //         "name":"ETH-PERP",
        //         "price":171.29,
        //         "priceIncrement":0.01,
        //         "quoteCurrency":null, // quote currency for spot markets
        //         "quoteVolume24h":8570651.12113,
        //         "sizeIncrement":0.001,
        //         "type":"future",
        //         "underlying":"ETH", // null for spot markets
        //         "volumeUsd24h":8570651.12113,
        //     }
        //
        let symbol = undefined;
        const marketId = this.safeString (ticker, 'name');
        if (marketId in this.markets_by_id) {
            market = this.markets_by_id[marketId];
        } else {
            const type = this.safeString (ticker, 'type');
            if (type === 'future') {
                symbol = marketId;
            } else {
                const base = this.safeCurrencyCode (this.safeString (ticker, 'baseCurrency'));
                const quote = this.safeCurrencyCode (this.safeString (ticker, 'quoteCurrency'));
                symbol = base + '/' + quote;
            }
        }
        if ((symbol === undefined) && (market !== undefined)) {
            symbol = market['symbol'];
        }
        const last = this.safeFloat (ticker, 'last');
        const timestamp = this.milliseconds ();
        return {
            'symbol': symbol,
            'timestamp': timestamp,
            'datetime': this.iso8601 (timestamp),
            'high': this.safeFloat (ticker, 'high'),
            'low': this.safeFloat (ticker, 'low'),
            'bid': this.safeFloat (ticker, 'bid'),
            'bidVolume': undefined,
            'ask': this.safeFloat (ticker, 'ask'),
            'askVolume': undefined,
            'vwap': undefined,
            'open': undefined,
            'close': last,
            'last': last,
            'previousClose': undefined,
            'change': undefined,
            'percentage': this.safeFloat (ticker, 'change24h'),
            'average': undefined,
            'baseVolume': undefined,
            'quoteVolume': this.safeFloat (ticker, 'quoteVolume24h'),
            'info': ticker,
        };
    }

    async fetchTicker (symbol, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market_name': market['id'],
        };
        const response = await this.publicGetMarketsMarketName (this.extend (request, params));
        //
        //     {
        //         "success":true,
        //         "result":{
        //             "ask":171.29,
        //             "baseCurrency":null, // base currency for spot markets
        //             "bid":171.24,
        //             "change1h":-0.0012244897959183673,
        //             "change24h":-0.031603346901854366,
        //             "changeBod":-0.03297013492914808,
        //             "enabled":true,
        //             "last":171.44,
        //             "name":"ETH-PERP",
        //             "price":171.29,
        //             "priceIncrement":0.01,
        //             "quoteCurrency":null, // quote currency for spot markets
        //             "quoteVolume24h":8570651.12113,
        //             "sizeIncrement":0.001,
        //             "type":"future",
        //             "underlying":"ETH", // null for spot markets
        //             "volumeUsd24h":8570651.12113,
        //         }
        //     }
        //
        const result = this.safeValue (response, 'result', {});
        return this.parseTicker (result, market);
    }

    parseTickers (tickers, symbols = undefined) {
        const result = [];
        for (let i = 0; i < tickers.length; i++) {
            result.push (this.parseTicker (tickers[i]));
        }
        return this.filterByArray (result, 'symbol', symbols);
    }

    async fetchTickers (symbols = undefined, params = {}) {
        await this.loadMarkets ();
        const response = await this.publicGetMarkets (params);
        //
        //     {
        //         'success': true,
        //         "result": [
        //             {
        //                 "ask":170.44,
        //                 "baseCurrency":"ETH",
        //                 "bid":170.41,
        //                 "change1h":-0.018485459257126403,
        //                 "change24h":-0.023825887743413515,
        //                 "changeBod":-0.037605872388481086,
        //                 "enabled":true,
        //                 "last":172.72,
        //                 "name":"ETH/USD",
        //                 "price":170.44,
        //                 "priceIncrement":0.01,
        //                 "quoteCurrency":"USD",
        //                 "quoteVolume24h":382802.0252,
        //                 "sizeIncrement":0.001,
        //                 "type":"spot",
        //                 "underlying":null,
        //                 "volumeUsd24h":382802.0252
        //             },
        //         ],
        //     }
        //
        const tickers = this.safeValue (response, 'result', []);
        return this.parseTickers (tickers, symbols);
    }

    async fetchOrderBook (symbol, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market_name': market['id'],
        };
        const response = await this.publicGetMarketsMarketNameOrderbook (this.extend (request, params));
        //
        //     {
        //         "success":true,
        //         "result":{
        //             "asks":[
        //                 [171.95,279.865],
        //                 [171.98,102.42],
        //                 [171.99,124.11],
        //             ],
        //             "bids":[
        //                 [171.93,69.749],
        //                 [171.9,288.325],
        //                 [171.88,87.47],
        //             ],
        //         }
        //     }
        //
        const result = this.safeValue (response, 'result', {});
        return this.parseOrderBook (result);
    }

    async fetchBalance (params = {}) {
        await this.loadMarkets ();
        const response = await this.privateGetWalletBalances (params);
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "coin": "USDTBEAR",
        //                 "free": 2320.2,
        //                 "total": 2340.2
        //             },
        //         ],
        //     }
        //
        const result = {
            'info': response,
        };
        const balances = this.safeValue (response, 'result', []);
        for (let i = 0; i < balances.length; i++) {
            const balance = balances[i];
            const code = this.safeCurrencyCode (this.safeString (balance, 'coin'));
            const account = this.account ();
            account['free'] = this.safeFloat (balance, 'free');
            account['total'] = this.safeFloat (balance, 'total');
            result[code] = account;
        }
        return this.parseBalance (result);
    }

    parseOHLCV (ohlcv, market = undefined, timeframe = '1m', since = undefined, limit = undefined) {
        //
        //     {
        //         "close":177.23,
        //         "high":177.45,
        //         "low":177.2,
        //         "open":177.43,
        //         "startTime":"2019-10-17T13:27:00+00:00",
        //         "time":1571318820000.0,
        //         "volume":0.0
        //     }
        //
        return [
            this.safeInteger (ohlcv, 'time'),
            this.safeFloat (ohlcv, 'open'),
            this.safeFloat (ohlcv, 'high'),
            this.safeFloat (ohlcv, 'low'),
            this.safeFloat (ohlcv, 'close'),
            this.safeFloat (ohlcv, 'volume'),
        ];
    }

    async fetchOHLCV (symbol, timeframe = '1m', since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market_name': market['id'],
            'resolution': this.timeframes[timeframe],
        };
        if (limit !== undefined) {
            request['limit'] = limit;
        }
        if (since !== undefined) {
            request['start_time'] = parseInt (since / 1000);
        }
        const response = await this.publicGetMarketsMarketNameCandles (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result":[
        //             {
        //                 "close":177.23,
        //                 "high":177.45,
        //                 "low":177.2,
        //                 "open":177.43,
        //                 "startTime":"2019-10-17T13:27:00+00:00",
        //                 "time":1571318820000.0,
        //                 "volume":0.0
        //             },
        //             {
        //                 "close":177.26,
        //                 "high":177.33,
        //                 "low":177.23,
        //                 "open":177.23,
        //                 "startTime":"2019-10-17T13:28:00+00:00",
        //                 "time":1571318880000.0,
        //                 "volume":0.0
        //             },
        //         ],
        //     }
        //
        const result = this.safeValue (response, 'result', []);
        return this.parseOHLCVs (result, market, timeframe, since, limit);
    }

    parseTrade (trade, market = undefined) {
        //
        // fetchTrades (public)
        //
        //     {
        //         "id":1715826,
        //         "liquidation":false,
        //         "price":171.62,
        //         "side":"buy",
        //         "size":2.095,
        //         "time":"2019-10-18T12:59:54.288166+00:00"
        //     }
        //
        // fetchMyTrades (private)
        //
        //     {
        //         "fee": 20.1374935,
        //         "feeRate": 0.0005,
        //         "future": "EOS-0329",
        //         "id": 11215,
        //         "liquidity": "taker",
        //         "market": "EOS-0329",
        //         "baseCurrency": null,
        //         "quoteCurrency": null,
        //         "orderId": 8436981,
        //         "price": 4.201,
        //         "side": "buy",
        //         "size": 9587,
        //         "time": "2019-03-27T19:15:10.204619+00:00",
        //         "type": "order"
        //     }
        //
        const id = this.safeString (trade, 'id');
        const timestamp = this.parse8601 (this.safeString (trade, 'time'));
        const price = this.safeFloat (trade, 'price');
        const amount = this.safeFloat (trade, 'size');
        const symbol = this.safeString (market, 'symbol');
        const side = this.safeString (trade, 'side');
        let cost = undefined;
        if (price !== undefined && amount !== undefined) {
            cost = price * amount;
        }
        const fee = undefined;
        return {
            'info': trade,
            'timestamp': timestamp,
            'datetime': this.iso8601 (timestamp),
            'symbol': symbol,
            'id': id,
            'order': undefined,
            'type': undefined,
            'takerOrMaker': undefined,
            'side': side,
            'price': price,
            'amount': amount,
            'cost': cost,
            'fee': fee,
        };
    }

    async fetchTrades (symbol, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market_name': market['id'],
        };
        if (since !== undefined) {
            request['start_time'] = since;
        }
        if (limit !== undefined) {
            request['limit'] = limit;
        }
        const response = await this.publicGetMarketsMarketNameTrades (this.extend (request, params));
        //
        //     {
        //         "success":true,
        //         "result":[
        //             {
        //                 "id":1715826,
        //                 "liquidation":false,
        //                 "price":171.62,
        //                 "side":"buy",
        //                 "size":2.095,
        //                 "time":"2019-10-18T12:59:54.288166+00:00"
        //             },
        //             {
        //                 "id":1715763,
        //                 "liquidation":false,
        //                 "price":171.89,
        //                 "side":"sell",
        //                 "size":1.477,
        //                 "time":"2019-10-18T12:58:38.443734+00:00"
        //             },
        //         ],
        //     }
        //
        const result = this.safeValue (response, 'result', []);
        return this.parseTrades (result, market, since, limit);
    }

    parseOrderStatus (status) {
        const statuses = {
            'new': 'open',
            'open': 'open',
            'closed': 'closed', // filled or canceled
        };
        return this.safeString (statuses, status, status);
    }

    parseOrder (order, market = undefined) {
        //
        // fetchOrder, fetchOrders, fetchOpenOrders, createOrder ("limit", "market")
        //
        //     {
        //         "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //         "filledSize": 0,
        //         "future": "XRP-PERP",
        //         "id": 9596912,
        //         "market": "XRP-PERP",
        //         "price": 0.306525,
        //         "remainingSize": 31431,
        //         "side": "sell",
        //         "size": 31431,
        //         "status": "open",
        //         "type": "limit",
        //         "reduceOnly": false,
        //         "ioc": false,
        //         "postOnly": false,
        //         "clientId": null,
        //     }
        //
        // createOrder (conditional, "stop", "trailingStop", or "takeProfit")
        //
        //     {
        //         "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //         "future": "XRP-PERP",
        //         "id": 9596912,
        //         "market": "XRP-PERP",
        //         "triggerPrice": 0.306525,
        //         "orderId": null,
        //         "side": "sell",
        //         "size": 31431,
        //         "status": "open",
        //         "type": "stop",
        //         "orderPrice": null,
        //         "error": null,
        //         "triggeredAt": null,
        //         "reduceOnly": false
        //     }
        //
        const id = this.safeString (order, 'id');
        const datetime = this.safeString (order, 'createdAt');
        const timestamp = this.parse8601 (datetime);
        const filled = this.safeFloat (order, 'filledSize');
        const remaining = this.safeFloat (order, 'remainingSize');
        const symbol = this.findSymbol (this.safeString (order, 'market'));
        const status = this.parseOrderStatus (this.safeString (order, 'status'));
        const side = this.safeString (order, 'side');
        const type = this.safeString (order, 'type');
        const amount = this.safeFloat (order, 'size');
        let cost = undefined;
        if (filled !== undefined && amount !== undefined) {
            cost = filled * amount;
        }
        let price = 0;
        // determine if its a stop-loss order
        if (type === 'stop') {
            price = this.safeFloat (order, 'triggerPrice');
        } else {
            price = this.safeFloat (order, 'price');
        }
        return {
            'info': order,
            'id': id,
            'timestamp': timestamp,
            'datetime': datetime,
            'lastTradeTimestamp': undefined,
            'symbol': symbol,
            'type': type,
            'side': side,
            'price': price,
            'amount': amount,
            'cost': cost,
            'average': undefined,
            'filled': filled,
            'remaining': remaining,
            'status': status,
            'fee': {
                'currency': undefined,
                'cost': undefined,
                'rate': undefined,
            },
            'trades': undefined,
        };
    }

    async createOrder (symbol, type, side, amount, price = undefined, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market': market['id'],
            'side': side, // "buy" or "sell"
            // 'price': 0.306525, // send null for market orders
            'type': type, // "limit", "market", "stop", "trailingStop", or "takeProfit"
            'size': parseFloat (this.amountToPrecision (symbol, amount)),
            // 'reduceOnly': false, // optional, default is false
            // 'ioc': false, // optional, default is false, limit or market orders only
            // 'postOnly': false, // optional, default is false, limit or market orders only
            // 'clientId': 'abcdef0123456789', // string, optional, client order id, limit or market orders only
        };
        const priceToPrecision = parseFloat (this.priceToPrecision (symbol, price));
        let method = 'privatePostConditionalOrders';
        if ((type === 'limit') || (type === 'market')) {
            method = 'privatePostOrders';
            request['price'] = priceToPrecision;
        } else if ((type === 'stop') || (type === 'takeProfit')) {
            request['triggerPrice'] = priceToPrecision;
            // request['orderPrice'] = number; // optional, order type is limit if this is specified, otherwise market
        } else if (type === 'trailingStop') {
            request['trailValue'] = priceToPrecision; // negative for "sell", positive for "buy"
        } else {
            throw new InvalidOrder (this.id + ' createOrder () does not support order type ' + type + ', only limit, market, stop, trailingStop, or takeProfit orders are supported');
        }
        const response = await this[method] (this.extend (request, params));
        //
        // orders
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //                 "filledSize": 0,
        //                 "future": "XRP-PERP",
        //                 "id": 9596912,
        //                 "market": "XRP-PERP",
        //                 "price": 0.306525,
        //                 "remainingSize": 31431,
        //                 "side": "sell",
        //                 "size": 31431,
        //                 "status": "open",
        //                 "type": "limit",
        //                 "reduceOnly": false,
        //                 "ioc": false,
        //                 "postOnly": false,
        //                 "clientId": null,
        //             }
        //         ]
        //     }
        //
        // conditional orders
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //                 "future": "XRP-PERP",
        //                 "id": 9596912,
        //                 "market": "XRP-PERP",
        //                 "triggerPrice": 0.306525,
        //                 "orderId": null,
        //                 "side": "sell",
        //                 "size": 31431,
        //                 "status": "open",
        //                 "type": "stop",
        //                 "orderPrice": null,
        //                 "error": null,
        //                 "triggeredAt": null,
        //                 "reduceOnly": false
        //             }
        //         ]
        //     }
        //
        //
        const result = this.safeValue (response, 'result', []);
        return this.parseOrder (result, market);
    }

    async cancelOrder (id, symbol = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {
            'id': parseInt (id),
        };
        const response = await this.privateDeleteOrdersOrderId (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": "Order queued for cancelation"
        //     }
        //
        const result = this.safeValue (response, 'result', {});
        return result;
    }

    async cancelAllOrders (symbol = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {
            // 'market': market['id'], // optional
            'conditionalOrdersOnly': false, // cancel conditional orders only
            'limitOrdersOnly': false, // cancel existing limit orders (non-conditional orders) only
        };
        let market = undefined;
        if (symbol !== undefined) {
            market = this.market (symbol);
            request['market'] = market['id'];
        }
        const response = await this.privateDeleteOrders (this.extend (request, params));
        const result = this.safeValue (response, 'result', {});
        //
        //     {
        //         "success": true,
        //         "result": "Orders queued for cancelation"
        //     }
        //
        return result;
    }

    async fetchOrder (id, symbol = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {
            'id': id,
        };
        const response = await this.privateGetOrdersOrderId (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": {
        //             "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //             "filledSize": 10,
        //             "future": "XRP-PERP",
        //             "id": 9596912,
        //             "market": "XRP-PERP",
        //             "price": 0.306525,
        //             "avgFillPrice": 0.306526,
        //             "remainingSize": 31421,
        //             "side": "sell",
        //             "size": 31431,
        //             "status": "open",
        //             "type": "limit",
        //             "reduceOnly": false,
        //             "ioc": false,
        //             "postOnly": false,
        //             "clientId": null
        //         }
        //     }
        //
        const result = this.safeValue (response, 'result', {});
        return this.parseOrder (result);
    }

    async fetchOpenOrders (symbol = undefined, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {};
        let market = undefined;
        if (symbol !== undefined) {
            market = this.market (symbol);
            request['market'] = market['id'];
        }
        const response = await this.privateGetOrders (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //                 "filledSize": 10,
        //                 "future": "XRP-PERP",
        //                 "id": 9596912,
        //                 "market": "XRP-PERP",
        //                 "price": 0.306525,
        //                 "avgFillPrice": 0.306526,
        //                 "remainingSize": 31421,
        //                 "side": "sell",
        //                 "size": 31431,
        //                 "status": "open",
        //                 "type": "limit",
        //                 "reduceOnly": false,
        //                 "ioc": false,
        //                 "postOnly": false,
        //                 "clientId": null
        //             }
        //         ]
        //     }
        //
        const result = this.safeValue (response, 'result', []);
        return this.parseOrders (result, market, since, limit);
    }

    async fetchOrders (symbol = undefined, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {};
        let market = undefined;
        if (symbol !== undefined) {
            market = this.market (symbol);
            request['market'] = market['id'];
        }
        if (limit !== undefined) {
            request['limit'] = limit; // default 100, max 100
        }
        if (since !== undefined) {
            request['start_time'] = parseInt (since / 1000);
        }
        const response = await this.privateGetOrdersHistory (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "createdAt": "2019-03-05T09:56:55.728933+00:00",
        //                 "filledSize": 10,
        //                 "future": "XRP-PERP",
        //                 "id": 9596912,
        //                 "market": "XRP-PERP",
        //                 "price": 0.306525,
        //                 "avgFillPrice": 0.306526,
        //                 "remainingSize": 31421,
        //                 "side": "sell",
        //                 "size": 31431,
        //                 "status": "open",
        //                 "type": "limit",
        //                 "reduceOnly": false,
        //                 "ioc": false,
        //                 "postOnly": false,
        //                 "clientId": null
        //             }
        //         ]
        //     }
        //
        const result = this.safeValue (response, 'result', []);
        return this.parseOrders (result, market, since, limit);
    }

    async fetchMyTrades (symbol = undefined, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const market = this.market (symbol);
        const request = {
            'market': market['id'],
        };
        if (limit !== undefined) {
            request['limit'] = limit;
        }
        if (since !== undefined) {
            request['start_time'] = parseInt (since / 1000);
        }
        const response = await this.privateGetFills (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": [
        //             {
        //                 "fee": 20.1374935,
        //                 "feeRate": 0.0005,
        //                 "future": "EOS-0329",
        //                 "id": 11215,
        //                 "liquidity": "taker",
        //                 "market": "EOS-0329",
        //                 "baseCurrency": null,
        //                 "quoteCurrency": null,
        //                 "orderId": 8436981,
        //                 "price": 4.201,
        //                 "side": "buy",
        //                 "size": 9587,
        //                 "time": "2019-03-27T19:15:10.204619+00:00",
        //                 "type": "order"
        //             }
        //         ]
        //     }
        //
        const trades = this.safeValue (response, 'result', []);
        return this.parseTrades (trades, market, since, limit);
    }

    async withdraw (code, amount, address, tag = undefined, params = {}) {
        await this.loadMarkets ();
        this.checkAddress (address);
        const currency = this.currency (code);
        const request = {
            'coin': currency['id'],
            'size': amount,
            'address': address,
            // 'password': 'string', // optional withdrawal password if it is required for your account
            // 'code': '192837', // optional 2fa code if it is required for your account
        };
        if (tag !== undefined) {
            request['tag'] = tag;
        }
        const response = await this.privatePostWalletWithdrawals (this.extend (request, params));
        //
        //     {
        //         "success": true,
        //         "result": {
        //             "coin": "USDTBEAR",
        //             "address": "0x83a127952d266A6eA306c40Ac62A4a70668FE3BE",
        //             "tag": "null",
        //             "fee": 0,
        //             "id": 1,
        //             "size": "20.2",
        //             "status": "requested",
        //             "time": "2019-03-05T09:56:55.728933+00:00",
        //             "txid": "null"
        //         }
        //     }
        //
        const result = this.safeValue (response, 'result', {});
        return this.parseTransaction (result, currency);
    }

    async fetchDepositAddress (code, params = {}) {
        await this.loadMarkets ();
        const currency = this.currency (code);
        const request = {
            'coin': currency['id'],
        };
        const response = await this.privateGetWalletDepositAddressCoin (this.extend (request, params));
        const result = this.safeValue (response, 'result', {});
        const address = this.safeString (result, 'address');
        const tag = this.safeString (result, 'tag');
        this.checkAddress (address);
        return {
            'currency': code,
            'address': address,
            'tag': tag,
            'info': result,
        };
    }

    parseTransaction (transaction) {
        const currency = this.safeString (transaction, 'coin');
        const code = this.safeCurrencyCode (currency);
        const id = this.safeInteger (transaction, 'id');
        const comment = this.safeString (transaction, 'notes');
        const amount = this.safeFloat (transaction, 'size');
        const status = this.safeString (transaction, 'status');
        const datetime = this.safeString (transaction, 'time');
        const timestamp = this.parse8601 (datetime);
        const txid = this.safeString (transaction, 'txid');
        const address = this.safeString (transaction, 'address');
        const tag = this.safeString (transaction, 'tag');
        const fee = this.safeFloat (transaction, 'fee');
        return {
            'info': transaction,
            'id': id,
            'txid': txid,
            'timestamp': timestamp,
            'datetime': datetime,
            'addressFrom': undefined,
            'address': undefined,
            'addressTo': address,
            'tagFrom': undefined,
            'tag': tag,
            'tagTo': undefined,
            'type': undefined,
            'amount': amount,
            'currency': code,
            'status': status,
            'updated': undefined,
            'comment': comment,
            'fee': {
                'currency': code,
                'cost': fee,
                'rate': undefined,
            },
        };
    }

    async fetchDeposits (code = undefined, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {};
        const response = await this.privateGetWalletDeposits (this.extend (request, params));
        const result = this.safeValue (response, 'result', []);
        return this.parseTransactions (result);
    }

    async fetchWithdrawals (symbol = undefined, since = undefined, limit = undefined, params = {}) {
        await this.loadMarkets ();
        const request = {};
        const response = await this.privateGetWalletWithdrawals (this.extend (request, params));
        const result = this.safeValue (response, 'result', []);
        return this.parseTransactions (result);
    }

    sign (path, api = 'public', method = 'GET', params = {}, headers = undefined, body = undefined) {
        let request = '/' + this.implodeParams (path, params);
        const query = this.omit (params, this.extractParams (path));
        let url = this.urls['api'] + request;
        if (method === 'GET') {
            if (Object.keys (query).length) {
                const suffix = '?' + this.urlencode (query);
                url += suffix;
                request += suffix;
            }
        }
        if (api === 'private') {
            if (method === 'POST') {
                body = this.json (query);
                this.checkRequiredCredentials ();
                request = '/api' + request;
                const tx = this.milliseconds ();
                const hmacString = this.encode (tx + method + request + body);
                const secret = this.encode (this.secret);
                const signature = this.hmac (hmacString, secret, 'sha256');
                headers = {
                    'FTX-KEY': this.apiKey,
                    'FTX-TS': tx,
                    'FTX-SIGN': signature,
                    'content-type': 'application/json',
                };
            } else {
                this.checkRequiredCredentials ();
                request = '/api' + request;
                const tx = this.milliseconds ();
                const hmacString = tx + method + request;
                const secret = this.encode (this.secret);
                const signature = this.hmac (hmacString, secret, 'sha256');
                headers = {
                    'FTX-KEY': this.apiKey,
                    'FTX-TS': tx,
                    'FTX-SIGN': signature,
                };
            }
        }
        return { 'url': url, 'method': method, 'body': body, 'headers': headers };
    }

    handleErrors (code, reason, url, method, headers, body, response, requestHeaders, requestBody) {
        if (response === undefined) {
            return; // fallback to the default error handler
        }
        //
        //     {"error":"Invalid parameter start_time","success":false}
        //     {"error":"Not enough balances","success":false}
        //
        const success = this.safeValue (response, 'success');
        if (!success) {
            const feedback = this.id + ' ' + this.json (response);
            const error = this.safeString (response, 'error');
            const exact = this.exceptions['exact'];
            if (error in exact) {
                throw new exact[error] (feedback);
            }
            const broad = this.exceptions['broad'];
            const broadKey = this.findBroadlyMatchedKey (broad, error);
            if (broadKey !== undefined) {
                throw new broad[broadKey] (feedback);
            }
            throw new ExchangeError (feedback); // unknown message
        }
    }
};
