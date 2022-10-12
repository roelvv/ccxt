# -*- coding: utf-8 -*-

# PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
# https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

from ccxt.pro.base.exchange import Exchange
import ccxt.async_support
from ccxt.pro.base.cache import ArrayCache, ArrayCacheBySymbolById, ArrayCacheByTimestamp
import hashlib
import json
from ccxt.base.errors import BadRequest
from ccxt.base.errors import InvalidNonce


class bittrex(Exchange, ccxt.async_support.bittrex):

    def describe(self):
        return self.deep_extend(super(bittrex, self).describe(), {
            'has': {
                'ws': True,
                'watchBalance': True,
                'watchHeartbeat': True,
                'watchMyTrades': True,
                'watchOHLCV': True,
                'watchOrderBook': True,
                'watchOrders': True,
                'watchTicker': True,
                'watchTickers': False,  # for now
                'watchTrades': True,
            },
            'urls': {
                'api': {
                    'ws': 'wss://socket-v3.bittrex.com/signalr/connect',
                    'signalr': 'https://socket-v3.bittrex.com/signalr',
                },
            },
            'api': {
                'signalr': {
                    'get': [
                        'negotiate',
                        'start',
                    ],
                },
            },
            'options': {
                'tradesLimit': 1000,
                'hub': 'c3',
                'I': self.milliseconds(),
            },
        })

    def get_signal_r_url(self, negotiation):
        connectionToken = self.safe_string(negotiation['response'], 'ConnectionToken')
        query = self.extend(negotiation['request'], {
            'connectionToken': connectionToken,
            # 'tid': self.milliseconds() % 10,
        })
        return self.urls['api']['ws'] + '?' + self.urlencode(query)

    def make_request(self, requestId, method, args):
        hub = self.safe_string(self.options, 'hub', 'c3')
        return {
            'H': hub,
            'M': method,
            'A': args,  # arguments
            'I': requestId,  # invocation request id
        }

    def make_request_to_subscribe(self, requestId, args):
        method = 'Subscribe'
        return self.make_request(requestId, method, args)

    def make_request_to_authenticate(self, requestId):
        timestamp = self.milliseconds()
        uuid = self.uuid()
        auth = str(timestamp) + uuid
        signature = self.hmac(self.encode(auth), self.encode(self.secret), hashlib.sha512)
        args = [self.apiKey, timestamp, uuid, signature]
        method = 'Authenticate'
        return self.make_request(requestId, method, args)

    def request_id(self):
        # their support said that reqid must be an int32, not documented
        reqid = self.sum(self.safe_integer(self.options, 'I', 0), 1)
        self.options['I'] = reqid
        return reqid

    async def send_request_to_subscribe(self, negotiation, messageHash, subscription, params={}):
        args = [messageHash]
        requestId = str(self.request_id())
        request = self.make_request_to_subscribe(requestId, [args])
        subscription = self.extend({
            'id': requestId,
            'negotiation': negotiation,
        }, subscription)
        url = self.get_signal_r_url(negotiation)
        return await self.watch(url, messageHash, request, messageHash, subscription)

    async def authenticate(self, params={}):
        await self.load_markets()
        request = await self.negotiate()
        return await self.send_request_to_authenticate(request, False, params)

    async def send_request_to_authenticate(self, negotiation, expired=False, params={}):
        url = self.get_signal_r_url(negotiation)
        client = self.client(url)
        messageHash = 'authenticate'
        future = self.safe_value(client.subscriptions, messageHash)
        if (future is None) or expired:
            future = client.future(messageHash)
            client.subscriptions[messageHash] = future
            requestId = str(self.request_id())
            request = self.make_request_to_authenticate(requestId)
            subscription = {
                'id': requestId,
                'params': params,
                'negotiation': negotiation,
                'method': self.handle_authenticate,
            }
            self.spawn(self.watch, url, messageHash, request, requestId, subscription)
        return await future

    async def send_authenticated_request_to_subscribe(self, authentication, messageHash, params={}):
        negotiation = self.safe_value(authentication, 'negotiation')
        subscription = {'params': params}
        return await self.send_request_to_subscribe(negotiation, messageHash, subscription, params)

    def handle_authenticate(self, client, message, subscription):
        requestId = self.safe_string(subscription, 'id')
        if requestId in client.subscriptions:
            del client.subscriptions[requestId]
        client.resolve(subscription, 'authenticate')

    async def handle_authentication_expiring_helper(self):
        negotiation = await self.negotiate()
        return await self.send_request_to_authenticate(negotiation, True)

    def handle_authentication_expiring(self, client, message):
        #
        #     {
        #         C: 'd-B1733F58-B,0|vT7,1|vT8,2|vBR,3',
        #         M: [{H: 'C3', M: 'authenticationExpiring', A: []}]
        #     }
        #
        # resend the authentication request and refresh the subscription
        #
        self.spawn(self.handle_authentication_expiring_helper)

    def create_signal_r_query(self, params={}):
        hub = self.safe_string(self.options, 'hub', 'c3')
        hubs = [
            {'name': hub},
        ]
        ms = self.milliseconds()
        return self.extend({
            'transport': 'webSockets',
            'connectionData': self.json(hubs),
            'clientProtocol': 1.5,
            '_': ms,  # no cache
            'tid': self.sum(ms % 10, 1),  # random
        }, params)

    async def negotiate(self, params={}):
        client = self.client(self.urls['api']['ws'])
        messageHash = 'negotiate'
        future = self.safe_value(client.subscriptions, messageHash)
        if future is None:
            future = client.future(messageHash)
            client.subscriptions[messageHash] = future
            request = self.create_signal_r_query(params)
            response = await self.signalrGetNegotiate(self.extend(request, params))
            #
            #     {
            #         Url: '/signalr/v1.1/signalr',
            #         ConnectionToken: 'lT/sa19+FcrEb4W53On2v+Pcc3d4lVCHV5/WJtmQw1RQNQMpm7K78w/WnvfTN2EgwQopTUiFX1dioHN7Bd1p8jAbfdxrqf5xHAMntJfOrw1tON0O',
            #         ConnectionId: 'a2afb0f7-346f-4f32-b7c7-01e04584b86a',
            #         KeepAliveTimeout: 20,
            #         DisconnectTimeout: 30,
            #         ConnectionTimeout: 110,
            #         TryWebSockets: True,
            #         ProtocolVersion: '1.5',
            #         TransportConnectTimeout: 5,
            #         LongPollDelay: 0
            #     }
            #
            result = {
                'request': request,
                'response': response,
            }
            client.resolve(result, messageHash)
        return await future

    async def start(self, negotiation, params={}):
        connectionToken = self.safe_string(negotiation['response'], 'ConnectionToken')
        request = self.create_signal_r_query(self.extend(negotiation['request'], {
            'connectionToken': connectionToken,
        }))
        return await self.signalrGetStart(request)

    async def watch_orders(self, symbol=None, since=None, limit=None, params={}):
        await self.load_markets()
        authentication = await self.authenticate()
        orders = await self.subscribe_to_orders(authentication, params)
        if self.newUpdates:
            limit = orders.getLimit(symbol, limit)
        return self.filter_by_symbol_since_limit(orders, symbol, since, limit, True)

    async def subscribe_to_orders(self, authentication, params={}):
        messageHash = 'order'
        return await self.send_authenticated_request_to_subscribe(authentication, messageHash, params)

    def handle_order(self, client, message):
        #
        #     {
        #         accountId: '2832c5c6-ac7a-493e-bc16-ebca06c73670',
        #         sequence: 41,
        #         delta: {
        #             id: 'b91eff76-10eb-4382-834a-b753b770283e',
        #             marketSymbol: 'BTC-USDT',
        #             direction: 'BUY',
        #             type: 'LIMIT',
        #             quantity: '0.01000000',
        #             limit: '3000.00000000',
        #             timeInForce: 'GOOD_TIL_CANCELLED',
        #             fillQuantity: '0.00000000',
        #             commission: '0.00000000',
        #             proceeds: '0.00000000',
        #             status: 'OPEN',
        #             createdAt: '2020-10-07T12:51:43.16Z',
        #             updatedAt: '2020-10-07T12:51:43.16Z'
        #         }
        #     }
        #
        delta = self.safe_value(message, 'delta', {})
        parsed = self.parse_order(delta)
        if self.orders is None:
            limit = self.safe_integer(self.options, 'ordersLimit', 1000)
            self.orders = ArrayCacheBySymbolById(limit)
        orders = self.orders
        orders.append(parsed)
        messageHash = 'order'
        client.resolve(self.orders, messageHash)

    async def watch_balance(self, params={}):
        await self.load_markets()
        authentication = await self.authenticate()
        return await self.subscribe_to_balance(authentication, params)

    async def subscribe_to_balance(self, authentication, params={}):
        messageHash = 'balance'
        return await self.send_authenticated_request_to_subscribe(authentication, messageHash, params)

    def handle_balance(self, client, message):
        #
        #     {
        #         accountId: '2832c5c6-ac7a-493e-bc16-ebca06c73670',
        #         sequence: 9,
        #         delta: {
        #             currencySymbol: 'USDT',
        #             total: '32.88918476',
        #             available: '2.82918476',
        #             updatedAt: '2020-10-06T13:49:20.29Z'
        #         }
        #     }
        #
        delta = self.safe_value(message, 'delta', {})
        currencyId = self.safe_string(delta, 'currencySymbol')
        code = self.safe_currency_code(currencyId)
        account = self.account()
        account['free'] = self.safe_string(delta, 'available')
        account['total'] = self.safe_string(delta, 'total')
        self.balance[code] = account
        self.balance = self.safe_balance(self.balance)
        messageHash = 'balance'
        client.resolve(self.balance, messageHash)

    async def watch_heartbeat(self, params={}):
        await self.load_markets()
        negotiation = await self.negotiate()
        return await self.subscribe_to_heartbeat(negotiation, params)

    async def subscribe_to_heartbeat(self, negotiation, params={}):
        await self.load_markets()
        url = self.get_signal_r_url(negotiation)
        requestId = str(self.milliseconds())
        messageHash = 'heartbeat'
        args = [messageHash]
        request = self.make_request_to_subscribe(requestId, [args])
        subscription = {
            'id': requestId,
            'params': params,
            'negotiation': negotiation,
        }
        return await self.watch(url, messageHash, request, messageHash, subscription)

    def handle_heartbeat(self, client, message):
        #
        # every 20 seconds(approx) if no other updates are sent
        #
        #     {}
        #
        client.resolve(message, 'heartbeat')

    async def watch_ticker(self, symbol, params={}):
        await self.load_markets()
        negotiation = await self.negotiate()
        return await self.subscribe_to_ticker(negotiation, symbol, params)

    async def subscribe_to_ticker(self, negotiation, symbol, params={}):
        await self.load_markets()
        market = self.market(symbol)
        name = 'ticker'
        messageHash = name + '_' + market['id']
        subscription = {
            'marketId': market['id'],
            'symbol': symbol,
            'params': params,
        }
        return await self.send_request_to_subscribe(negotiation, messageHash, subscription)

    def handle_ticker(self, client, message):
        #
        # summary subscription update
        #
        #     ...
        #
        # ticker subscription update
        #
        #     {
        #         symbol: 'BTC-USDT',
        #         lastTradeRate: '10701.02140008',
        #         bidRate: '10701.02140007',
        #         askRate: '10705.71049998'
        #     }
        #
        ticker = self.parse_ticker(message)
        symbol = ticker['symbol']
        market = self.market(symbol)
        self.tickers[symbol] = ticker
        name = 'ticker'
        messageHash = name + '_' + market['id']
        client.resolve(ticker, messageHash)

    async def watch_ohlcv(self, symbol, timeframe='1m', since=None, limit=None, params={}):
        await self.load_markets()
        negotiation = await self.negotiate()
        ohlcv = await self.subscribe_to_ohlcv(negotiation, symbol, timeframe, params)
        if self.newUpdates:
            limit = ohlcv.getLimit(symbol, limit)
        return self.filter_by_since_limit(ohlcv, since, limit, 0, True)

    async def subscribe_to_ohlcv(self, negotiation, symbol, timeframe='1m', params={}):
        await self.load_markets()
        market = self.market(symbol)
        interval = self.timeframes[timeframe]
        name = 'candle'
        messageHash = name + '_' + market['id'] + '_' + interval
        subscription = {
            'symbol': symbol,
            'timeframe': timeframe,
            'messageHash': messageHash,
            'params': params,
        }
        return await self.send_request_to_subscribe(negotiation, messageHash, subscription)

    def handle_ohlcv(self, client, message):
        #
        #     {
        #         sequence: 28286,
        #         marketSymbol: 'BTC-USD',
        #         interval: 'MINUTE_1',
        #         delta: {
        #             startsAt: '2020-10-05T18:52:00Z',
        #             open: '10706.62600000',
        #             high: '10706.62600000',
        #             low: '10703.25900000',
        #             close: '10703.26000000',
        #             volume: '0.86822264',
        #             quoteVolume: '9292.84594774'
        #         }
        #     }
        #
        name = 'candle'
        marketId = self.safe_string(message, 'marketSymbol')
        symbol = self.safe_symbol(marketId, None, '-')
        interval = self.safe_string(message, 'interval')
        messageHash = name + '_' + marketId + '_' + interval
        timeframe = self.find_timeframe(interval)
        delta = self.safe_value(message, 'delta', {})
        parsed = self.parse_ohlcv(delta)
        self.ohlcvs[symbol] = self.safe_value(self.ohlcvs, symbol, {})
        stored = self.safe_value(self.ohlcvs[symbol], timeframe)
        if stored is None:
            limit = self.safe_integer(self.options, 'OHLCVLimit', 1000)
            stored = ArrayCacheByTimestamp(limit)
            self.ohlcvs[symbol][timeframe] = stored
        stored.append(parsed)
        client.resolve(stored, messageHash)

    async def watch_trades(self, symbol, since=None, limit=None, params={}):
        await self.load_markets()
        negotiation = await self.negotiate()
        trades = await self.subscribe_to_trades(negotiation, symbol, params)
        if self.newUpdates:
            limit = trades.getLimit(symbol, limit)
        return self.filter_by_since_limit(trades, since, limit, 'timestamp', True)

    async def subscribe_to_trades(self, negotiation, symbol, params={}):
        await self.load_markets()
        market = self.market(symbol)
        name = 'trade'
        messageHash = name + '_' + market['id']
        subscription = {
            'symbol': symbol,
            'messageHash': messageHash,
            'params': params,
        }
        return await self.send_request_to_subscribe(negotiation, messageHash, subscription)

    def handle_trades(self, client, message):
        #
        #     {
        #         deltas: [
        #             {
        #                 id: '5bf67885-a0a8-4c62-b73d-534e480e3332',
        #                 executedAt: '2020-10-05T23:02:17.49Z',
        #                 quantity: '0.00166790',
        #                 rate: '10763.97000000',
        #                 takerSide: 'BUY'
        #             }
        #         ],
        #         sequence: 24391,
        #         marketSymbol: 'BTC-USD'
        #     }
        #
        deltas = self.safe_value(message, 'deltas', [])
        marketId = self.safe_string(message, 'marketSymbol')
        symbol = self.safe_symbol(marketId, None, '-')
        market = self.market(symbol)
        name = 'trade'
        messageHash = name + '_' + marketId
        stored = self.safe_value(self.trades, symbol)
        if stored is None:
            limit = self.safe_integer(self.options, 'tradesLimit', 1000)
            stored = ArrayCache(limit)
        trades = self.parse_trades(deltas, market)
        for i in range(0, len(trades)):
            stored.append(trades[i])
        self.trades[symbol] = stored
        client.resolve(stored, messageHash)

    async def watch_my_trades(self, symbol=None, since=None, limit=None, params={}):
        await self.load_markets()
        authentication = await self.authenticate()
        trades = await self.subscribe_to_my_trades(authentication, params)
        if self.newUpdates:
            limit = trades.getLimit(symbol, limit)
        return self.filter_by_symbol_since_limit(trades, symbol, since, limit, True)

    async def subscribe_to_my_trades(self, authentication, params={}):
        messageHash = 'execution'
        return await self.send_authenticated_request_to_subscribe(authentication, messageHash, params)

    def handle_my_trades(self, client, message):
        #
        #     {
        #         accountId: '2832c5c6-ac7a-493e-bc16-ebca06c73670',
        #         sequence: 42,
        #         deltas: [
        #             {
        #                 id: '5bf67885-a0a8-4c62-b73d-534e480e3332',
        #                 marketSymbol: 'BTC-USDT',
        #                 executedAt: '2020-10-05T23:02:17.49Z',
        #                 quantity: '0.00166790',
        #                 rate: '10763.97000000',
        #                 orderId: "string(uuid)",
        #                 commission: '0.00000000',
        #                 isTaker: False
        #             }
        #         ]
        #     }
        #
        deltas = self.safe_value(message, 'deltas', {})
        trades = self.parse_trades(deltas)
        stored = self.myTrades
        if stored is None:
            limit = self.safe_integer(self.options, 'tradesLimit', 1000)
            stored = ArrayCacheBySymbolById(limit)
            self.myTrades = stored
        for i in range(0, len(trades)):
            stored.append(trades[i])
        messageHash = 'execution'
        client.resolve(stored, messageHash)

    async def watch_order_book(self, symbol, limit=None, params={}):
        limit = 25 if (limit is None) else limit  # 25 by default
        if (limit != 1) and (limit != 25) and (limit != 500):
            raise BadRequest(self.id + ' watchOrderBook() limit argument must be None, 1, 25 or 500, default is 25')
        await self.load_markets()
        negotiation = await self.negotiate()
        #
        #     1. Subscribe to the relevant socket streams
        #     2. Begin to queue up messages without processing them
        #     3. Call the equivalent v3 REST API and record both the results and the value of the returned Sequence header. Refer to the descriptions of individual streams to find the corresponding REST API. Note that you must call the REST API with the same parameters as you used to subscribed to the stream to get the right snapshot. For example, orderbook snapshots of different depths will have different sequence numbers.
        #     4. If the Sequence header is less than the sequence number of the first queued socket message received(unlikely), discard the results of step 3 and then repeat step 3 until self check passes.
        #     5. Discard all socket messages where the sequence number is less than or equal to the Sequence header retrieved from the REST call
        #     6. Apply the remaining socket messages in order on top of the results of the REST call. The objects received in the socket deltas have the same schemas as the objects returned by the REST API. Each socket delta is a snapshot of an object. The identity of the object is defined by a unique key made up of one or more fields in the message(see documentation of individual streams for details). To apply socket deltas to a local cache of data, simply replace the objects in the cache with those coming from the socket where the keys match.
        #     7. Continue to apply messages as they are received from the socket as long as sequence number on the stream is always increasing by 1 each message(Note: for private streams, the sequence number is scoped to a single account or subaccount).
        #     8. If a message is received that is not the next in order, return to step 2 in self process
        #
        orderbook = await self.subscribe_to_order_book(negotiation, symbol, limit, params)
        return orderbook.limit(limit)

    async def subscribe_to_order_book(self, negotiation, symbol, limit=None, params={}):
        await self.load_markets()
        market = self.market(symbol)
        name = 'orderbook'
        messageHash = name + '_' + market['id'] + '_' + str(limit)
        subscription = {
            'symbol': symbol,
            'messageHash': messageHash,
            'method': self.handle_subscribe_to_order_book,
            'limit': limit,
            'params': params,
        }
        return await self.send_request_to_subscribe(negotiation, messageHash, subscription)

    async def fetch_order_book_snapshot(self, client, message, subscription):
        symbol = self.safe_string(subscription, 'symbol')
        limit = self.safe_integer(subscription, 'limit')
        messageHash = self.safe_string(subscription, 'messageHash')
        try:
            # 2. Initiate a REST request to get the snapshot data of Level 2 order book.
            # todo: self is a synch blocking call in ccxt.php - make it async
            snapshot = await self.fetch_order_book(symbol, limit)
            orderbook = self.orderbooks[symbol]
            messages = orderbook.cache
            # make sure we have at least one delta before fetching the snapshot
            # otherwise we cannot synchronize the feed with the snapshot
            # and that will lead to a bidask cross as reported here
            # https://github.com/ccxt/ccxt/issues/6762
            firstMessage = self.safe_value(messages, 0, {})
            sequence = self.safe_integer(firstMessage, 'sequence')
            nonce = self.safe_integer(snapshot, 'nonce')
            # if the received snapshot is earlier than the first cached delta
            # then we cannot align it with the cached deltas and we need to
            # retry synchronizing in maxAttempts
            if (sequence is not None) and (nonce < sequence):
                options = self.safe_value(self.options, 'fetchOrderBookSnapshot', {})
                maxAttempts = self.safe_integer(options, 'maxAttempts', 3)
                numAttempts = self.safe_integer(subscription, 'numAttempts', 0)
                # retry to syncrhonize if we haven't reached maxAttempts yet
                if numAttempts < maxAttempts:
                    # safety guard
                    if messageHash in client.subscriptions:
                        numAttempts = self.sum(numAttempts, 1)
                        subscription['numAttempts'] = numAttempts
                        client.subscriptions[messageHash] = subscription
                        self.spawn(self.fetch_order_book_snapshot, client, message, subscription)
                else:
                    # raise upon failing to synchronize in maxAttempts
                    raise InvalidNonce(self.id + ' failed to synchronize WebSocket feed with the snapshot for symbol ' + symbol + ' in ' + str(maxAttempts) + ' attempts')
            else:
                orderbook.reset(snapshot)
                # unroll the accumulated deltas
                # 3. Playback the cached Level 2 data flow.
                for i in range(0, len(messages)):
                    message = messages[i]
                    self.handle_order_book_message(client, message, orderbook)
                self.orderbooks[symbol] = orderbook
                client.resolve(orderbook, messageHash)
        except Exception as e:
            client.reject(e, messageHash)

    def handle_subscribe_to_order_book(self, client, message, subscription):
        symbol = self.safe_string(subscription, 'symbol')
        limit = self.safe_integer(subscription, 'limit')
        if symbol in self.orderbooks:
            del self.orderbooks[symbol]
        self.orderbooks[symbol] = self.order_book({}, limit)
        self.spawn(self.fetch_order_book_snapshot, client, message, subscription)

    def handle_delta(self, bookside, delta):
        #
        #     {
        #         quantity: '0.05100000',
        #         rate: '10694.86410031'
        #     }
        #
        price = self.safe_float(delta, 'rate')
        amount = self.safe_float(delta, 'quantity')
        bookside.store(price, amount)

    def handle_deltas(self, bookside, deltas):
        #
        #     [
        #         {quantity: '0.05100000', rate: '10694.86410031'},
        #         {quantity: '0', rate: '10665.72578226'}
        #     ]
        #
        for i in range(0, len(deltas)):
            self.handle_delta(bookside, deltas[i])

    def handle_order_book(self, client, message):
        #
        #     {
        #         marketSymbol: 'BTC-USDT',
        #         depth: 25,
        #         sequence: 3009387,
        #         bidDeltas: [
        #             {quantity: '0.05100000', rate: '10694.86410031'},
        #             {quantity: '0', rate: '10665.72578226'}
        #         ],
        #         askDeltas: []
        #     }
        #
        marketId = self.safe_string(message, 'marketSymbol')
        symbol = self.safe_symbol(marketId, None, '-')
        limit = self.safe_integer(message, 'depth')
        orderbook = self.safe_value(self.orderbooks, symbol)
        if orderbook is None:
            orderbook = self.order_book({}, limit)
        if orderbook['nonce'] is not None:
            self.handle_order_book_message(client, message, orderbook)
        else:
            orderbook.cache.append(message)

    def handle_order_book_message(self, client, message, orderbook):
        #
        #     {
        #         marketSymbol: 'BTC-USDT',
        #         depth: 25,
        #         sequence: 3009387,
        #         bidDeltas: [
        #             {quantity: '0.05100000', rate: '10694.86410031'},
        #             {quantity: '0', rate: '10665.72578226'}
        #         ],
        #         askDeltas: []
        #     }
        #
        marketId = self.safe_string(message, 'marketSymbol')
        depth = self.safe_string(message, 'depth')
        name = 'orderbook'
        messageHash = name + '_' + marketId + '_' + depth
        nonce = self.safe_integer(message, 'sequence')
        if nonce > orderbook['nonce']:
            self.handle_deltas(orderbook['asks'], self.safe_value(message, 'askDeltas', []))
            self.handle_deltas(orderbook['bids'], self.safe_value(message, 'bidDeltas', []))
            orderbook['nonce'] = nonce
            client.resolve(orderbook, messageHash)
        return orderbook

    async def handle_system_status_helper(self):
        negotiation = await self.negotiate()
        await self.start(negotiation)

    def handle_system_status(self, client, message):
        # send signalR protocol start() call
        self.spawn(self.handle_system_status_helper)
        return message

    def handle_subscription_status(self, client, message):
        #
        # success
        #
        #     {R: [{Success: True, ErrorCode: null}], I: '1601891513224'}
        #
        # failure
        # todo add error handling and future rejections
        #
        #     {
        #         I: '1601942374563',
        #         E: "There was an error invoking Hub method 'c3.Authenticate'."
        #     }
        #
        I = self.safe_string(message, 'I')  # noqa: E741
        subscription = self.safe_value(client.subscriptions, I)
        if subscription is None:
            subscriptionsById = self.index_by(client.subscriptions, 'id')
            subscription = self.safe_value(subscriptionsById, I, {})
        else:
            # clear if subscriptionHash == requestId(one-time request)
            del client.subscriptions[I]
        method = self.safe_value(subscription, 'method')
        if method is None:
            client.resolve(message, I)
        else:
            method(client, message, subscription)
        return message

    def handle_message(self, client, message):
        #
        # subscription confirmation
        #
        #     {
        #         R: [
        #             {Success: True, ErrorCode: null}
        #         ],
        #         I: '1601899375696'
        #     }
        #
        # heartbeat subscription update
        #
        #     {
        #         C: 'd-6010FB90-B,0|o_b,0|o_c,2|8,1F4E',
        #         M: [
        #             {H: 'C3', M: 'heartbeat', A: []}
        #         ]
        #     }
        #
        # heartbeat empty message
        #
        #     {}
        #
        # subscription update
        #
        #     {
        #         C: 'd-ED78B69D-E,0|rq4,0|rq5,2|puI,60C',
        #         M: [
        #             {
        #                 H: 'C3',
        #                 M: 'ticker',  # orderBook, trade, candle, balance, order
        #                 A: [
        #                     'q1YqrsxNys9RslJyCnHWDQ12CVHSUcpJLC4JKUpMSQ1KLEkFShkamBsa6VkYm5paGJuZAhUkZaYgpAws9QwszAwsDY1MgFKJxdlIuiz0jM3MLIHATKkWAA=='
        #                 ]
        #             }
        #         ]
        #     }
        #
        # authentication expiry notification
        #
        #     {
        #         C: 'd-B1733F58-B,0|vT7,1|vT8,2|vBR,3',
        #         M: [{H: 'C3', M: 'authenticationExpiring', A: []}]
        #     }
        #
        methods = {
            'authenticationExpiring': self.handle_authentication_expiring,
            'order': self.handle_order,
            'balance': self.handle_balance,
            'trade': self.handle_trades,
            'candle': self.handle_ohlcv,
            'orderBook': self.handle_order_book,
            'heartbeat': self.handle_heartbeat,
            'ticker': self.handle_ticker,
            'execution': self.handle_my_trades,
        }
        M = self.safe_value(message, 'M', [])
        for i in range(0, len(M)):
            methodType = self.safe_value(M[i], 'M')
            method = self.safe_value(methods, methodType)
            if method is not None:
                if methodType == 'heartbeat':
                    method(client, message)
                elif methodType == 'authenticationExpiring':
                    method(client, message)
                else:
                    A = self.safe_value(M[i], 'A', [])
                    for k in range(0, len(A)):
                        inflated = self.inflate64(A[k])
                        update = json.loads(inflated)
                        method(client, update)
        # resolve invocations by request id
        if 'I' in message:
            self.handle_subscription_status(client, message)
        if 'S' in message:
            self.handle_system_status(client, message)
        keys = list(message.keys())
        numKeys = len(keys)
        if numKeys < 1:
            self.handle_heartbeat(client, message)
