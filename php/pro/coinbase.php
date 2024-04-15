<?php

namespace ccxt\pro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use ccxt\ExchangeError;
use ccxt\ArgumentsRequired;
use React\Async;
use React\Promise\PromiseInterface;

class coinbase extends \ccxt\async\coinbase {

    public function describe() {
        return $this->deep_extend(parent::describe(), array(
            'has' => array(
                'ws' => true,
                'cancelAllOrdersWs' => false,
                'cancelOrdersWs' => false,
                'cancelOrderWs' => false,
                'createOrderWs' => false,
                'editOrderWs' => false,
                'fetchBalanceWs' => false,
                'fetchOpenOrdersWs' => false,
                'fetchOrderWs' => false,
                'fetchTradesWs' => false,
                'watchBalance' => false,
                'watchMyTrades' => false,
                'watchOHLCV' => false,
                'watchOrderBook' => true,
                'watchOrders' => true,
                'watchTicker' => true,
                'watchTickers' => true,
                'watchTrades' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://advanced-trade-ws.coinbase.com',
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'ordersLimit' => 1000,
                'myTradesLimit' => 1000,
                'sides' => array(
                    'bid' => 'bids',
                    'offer' => 'asks',
                ),
            ),
        ));
    }

    public function subscribe($name, $symbol = null, $params = array ()) {
        return Async\async(function () use ($name, $symbol, $params) {
            /**
             * @ignore
             * subscribes to a websocket channel
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-overview#$subscribe
             * @param {string} $name the $name of the channel
             * @param {string|string[]} [$symbol] unified $market $symbol
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array} subscription to a websocket channel
             */
            Async\await($this->load_markets());
            $this->check_required_credentials();
            $market = null;
            $messageHash = $name;
            $productIds = array();
            if (gettype($symbol) === 'array' && array_keys($symbol) === array_keys(array_keys($symbol))) {
                $symbols = $this->market_symbols($symbol);
                $marketIds = $this->market_ids($symbols);
                $productIds = $marketIds;
                $messageHash = $messageHash . '::' . implode(',', $symbol);
            } elseif ($symbol !== null) {
                $market = $this->market($symbol);
                $messageHash = $name . '::' . $market['id'];
                $productIds = [ $market['id'] ];
            }
            $url = $this->urls['api']['ws'];
            $timestamp = $this->number_to_string($this->seconds());
            $isCloudAPiKey = (mb_strpos($this->apiKey, 'organizations/') !== false) || (str_starts_with($this->secret, '-----BEGIN'));
            $auth = $timestamp . $name . implode(',', $productIds);
            $subscribe = array(
                'type' => 'subscribe',
                'product_ids' => $productIds,
                'channel' => $name,
                // 'api_key' => $this->apiKey,
                // 'timestamp' => $timestamp,
                // 'signature' => $this->hmac($this->encode($auth), $this->encode($this->secret), 'sha256'),
            );
            if (!$isCloudAPiKey) {
                $subscribe['api_key'] = $this->apiKey;
                $subscribe['timestamp'] = $timestamp;
                $subscribe['signature'] = $this->hmac($this->encode($auth), $this->encode($this->secret), 'sha256');
            } else {
                if (str_starts_with($this->apiKey, '-----BEGIN')) {
                    throw new ArgumentsRequired($this->id . ' apiKey should contain the $name (eg => organizations/3b910e93....) and not the public key');
                }
                $currentToken = $this->safe_string($this->options, 'wsToken');
                $tokenTimestamp = $this->safe_integer($this->options, 'wsTokenTimestamp', 0);
                $seconds = $this->seconds();
                if ($currentToken === null || $tokenTimestamp + 120 < $seconds) {
                    // we should generate new $token
                    $token = $this->create_auth_token($seconds);
                    $this->options['wsToken'] = $token;
                    $this->options['wsTokenTimestamp'] = $seconds;
                }
                $subscribe['jwt'] = $this->safe_string($this->options, 'wsToken');
            }
            return Async\await($this->watch($url, $messageHash, $subscribe, $messageHash));
        }) ();
    }

    public function watch_ticker(string $symbol, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $params) {
            /**
             * watches a price ticker, a statistical calculation with the information calculated over the past 24 hours for a specific market
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-channels#ticker-channel
             * @param {string} [$symbol] unified $symbol of the market to fetch the ticker for
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array} a ~@link https://docs.ccxt.com/#/?id=ticker-structure ticker structure~
             */
            $name = 'ticker';
            return Async\await($this->subscribe($name, $symbol, $params));
        }) ();
    }

    public function watch_tickers(?array $symbols = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbols, $params) {
            /**
             * watches a price ticker, a statistical calculation with the information calculated over the past 24 hours for a specific market
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-channels#ticker-batch-channel
             * @param {string[]} [$symbols] unified symbol of the market to fetch the ticker for
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array} a ~@link https://docs.ccxt.com/#/?id=ticker-structure ticker structure~
             */
            if ($symbols === null) {
                $symbols = $this->symbols;
            }
            $name = 'ticker_batch';
            $tickers = Async\await($this->subscribe($name, $symbols, $params));
            if ($this->newUpdates) {
                return $tickers;
            }
            return $this->tickers;
        }) ();
    }

    public function handle_tickers($client, $message) {
        //
        //    {
        //        "channel" => "ticker",
        //        "client_id" => "",
        //        "timestamp" => "2023-02-09T20:30:37.167359596Z",
        //        "sequence_num" => 0,
        //        "events" => array(
        //            {
        //                "type" => "snapshot",
        //                "tickers" => array(
        //                    {
        //                        "type" => "ticker",
        //                        "product_id" => "BTC-USD",
        //                        "price" => "21932.98",
        //                        "volume_24_h" => "16038.28770938",
        //                        "low_24_h" => "21835.29",
        //                        "high_24_h" => "23011.18",
        //                        "low_52_w" => "15460",
        //                        "high_52_w" => "48240",
        //                        "price_percent_chg_24_h" => "-4.15775596190603"
        // new 2024-04-12
        //                        "best_bid":"21835.29",
        //                        "best_bid_quantity" => "0.02000000",
        //                        "best_ask":"23011.18",
        //                        "best_ask_quantity" => "0.01500000"
        //                    }
        //                )
        //            }
        //        )
        //    }
        //
        //    {
        //        "channel" => "ticker_batch",
        //        "client_id" => "",
        //        "timestamp" => "2023-03-01T12:15:18.382173051Z",
        //        "sequence_num" => 0,
        //        "events" => array(
        //            {
        //                "type" => "snapshot",
        //                "tickers" => array(
        //                    {
        //                        "type" => "ticker",
        //                        "product_id" => "DOGE-USD",
        //                        "price" => "0.08212",
        //                        "volume_24_h" => "242556423.3",
        //                        "low_24_h" => "0.07989",
        //                        "high_24_h" => "0.08308",
        //                        "low_52_w" => "0.04908",
        //                        "high_52_w" => "0.1801",
        //                        "price_percent_chg_24_h" => "0.50177456859626"
        // new 2024-04-12
        //                        "best_bid":"0.07989",
        //                        "best_bid_quantity" => "500.0",
        //                        "best_ask":"0.08308",
        //                        "best_ask_quantity" => "300.0"
        //                    }
        //                )
        //            }
        //        )
        //    }
        //
        $channel = $this->safe_string($message, 'channel');
        $events = $this->safe_value($message, 'events', array());
        $newTickers = array();
        for ($i = 0; $i < count($events); $i++) {
            $tickersObj = $events[$i];
            $tickers = $this->safe_value($tickersObj, 'tickers', array());
            for ($j = 0; $j < count($tickers); $j++) {
                $ticker = $tickers[$j];
                $result = $this->parse_ws_ticker($ticker);
                $symbol = $result['symbol'];
                $this->tickers[$symbol] = $result;
                $wsMarketId = $this->safe_string($ticker, 'product_id');
                $messageHash = $channel . '::' . $wsMarketId;
                $newTickers[] = $result;
                $client->resolve ($result, $messageHash);
                if (str_ends_with($messageHash, 'USD')) {
                    $client->resolve ($result, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
                }
            }
        }
        $messageHashes = $this->find_message_hashes($client, 'ticker_batch::');
        for ($i = 0; $i < count($messageHashes); $i++) {
            $messageHash = $messageHashes[$i];
            $parts = explode('::', $messageHash);
            $symbolsString = $parts[1];
            $symbols = explode(',', $symbolsString);
            $tickers = $this->filter_by_array($newTickers, 'symbol', $symbols);
            if (!$this->is_empty($tickers)) {
                $client->resolve ($tickers, $messageHash);
                if (str_ends_with($messageHash, 'USD')) {
                    $client->resolve ($tickers, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
                }
            }
        }
        return $message;
    }

    public function parse_ws_ticker($ticker, $market = null) {
        //
        //     {
        //         "type" => "ticker",
        //         "product_id" => "DOGE-USD",
        //         "price" => "0.08212",
        //         "volume_24_h" => "242556423.3",
        //         "low_24_h" => "0.07989",
        //         "high_24_h" => "0.08308",
        //         "low_52_w" => "0.04908",
        //         "high_52_w" => "0.1801",
        //         "price_percent_chg_24_h" => "0.50177456859626"
        // new 2024-04-12
        //         "best_bid":"0.07989",
        //         "best_bid_quantity" => "500.0",
        //         "best_ask":"0.08308",
        //         "best_ask_quantity" => "300.0"
        //     }
        //
        $marketId = $this->safe_string($ticker, 'product_id');
        $timestamp = null;
        $last = $this->safe_number($ticker, 'price');
        return $this->safe_ticker(array(
            'info' => $ticker,
            'symbol' => $this->safe_symbol($marketId, $market, '-'),
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'high' => $this->safe_string($ticker, 'high_24_h'),
            'low' => $this->safe_string($ticker, 'low_24_h'),
            'bid' => $this->safe_string($ticker, 'best_bid'),
            'bidVolume' => $this->safe_string($ticker, 'best_bid_quantity'),
            'ask' => $this->safe_string($ticker, 'best_ask'),
            'askVolume' => $this->safe_string($ticker, 'best_ask_quantity'),
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => $this->safe_string($ticker, 'price_percent_chg_24_h'),
            'average' => null,
            'baseVolume' => $this->safe_string($ticker, 'volume_24_h'),
            'quoteVolume' => null,
        ));
    }

    public function watch_trades(string $symbol, ?int $since = null, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            /**
             * get the list of most recent $trades for a particular $symbol
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-channels#market-$trades-channel
             * @param {string} $symbol unified $symbol of the market to fetch $trades for
             * @param {int} [$since] timestamp in ms of the earliest trade to fetch
             * @param {int} [$limit] the maximum amount of $trades to fetch
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array[]} a list of ~@link https://docs.ccxt.com/#/?id=public-$trades trade structures~
             */
            Async\await($this->load_markets());
            $symbol = $this->symbol($symbol);
            $name = 'market_trades';
            $trades = Async\await($this->subscribe($name, $symbol, $params));
            if ($this->newUpdates) {
                $limit = $trades->getLimit ($symbol, $limit);
            }
            return $this->filter_by_since_limit($trades, $since, $limit, 'timestamp', true);
        }) ();
    }

    public function watch_orders(?string $symbol = null, ?int $since = null, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            /**
             * watches information on multiple $orders made by the user
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-channels#user-channel
             * @param {string} [$symbol] unified market $symbol of the market $orders were made in
             * @param {int} [$since] the earliest time in ms to fetch $orders for
             * @param {int} [$limit] the maximum number of order structures to retrieve
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array[]} a list of ~@link https://docs.ccxt.com/#/?id=order-structure order structures~
             */
            Async\await($this->load_markets());
            $name = 'user';
            $orders = Async\await($this->subscribe($name, $symbol, $params));
            if ($this->newUpdates) {
                $limit = $orders->getLimit ($symbol, $limit);
            }
            return $this->filter_by_since_limit($orders, $since, $limit, 'timestamp', true);
        }) ();
    }

    public function watch_order_book(string $symbol, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $limit, $params) {
            /**
             * watches information on open orders with bid (buy) and ask (sell) prices, volumes and other data
             * @see https://docs.cloud.coinbase.com/advanced-trade-api/docs/ws-channels#level2-channel
             * @param {string} $symbol unified $symbol of the $market to fetch the order book for
             * @param {int} [$limit] the maximum amount of order book entries to return
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array} A dictionary of ~@link https://docs.ccxt.com/#/?id=order-book-structure order book structures~ indexed by $market symbols
             */
            Async\await($this->load_markets());
            $name = 'level2';
            $market = $this->market($symbol);
            $symbol = $market['symbol'];
            $orderbook = Async\await($this->subscribe($name, $symbol, $params));
            return $orderbook->limit ();
        }) ();
    }

    public function handle_trade($client, $message) {
        //
        //    {
        //        "channel" => "market_trades",
        //        "client_id" => "",
        //        "timestamp" => "2023-02-09T20:19:35.39625135Z",
        //        "sequence_num" => 0,
        //        "events" => array(
        //            {
        //                "type" => "snapshot",
        //                "trades" => array(
        //                    {
        //                        "trade_id" => "000000000",
        //                        "product_id" => "ETH-USD",
        //                        "price" => "1260.01",
        //                        "size" => "0.3",
        //                        "side" => "BUY",
        //                        "time" => "2019-08-14T20:42:27.265Z",
        //                    }
        //                )
        //            }
        //        )
        //    }
        //
        $events = $this->safe_value($message, 'events');
        $event = $this->safe_value($events, 0);
        $trades = $this->safe_value($event, 'trades');
        $trade = $this->safe_value($trades, 0);
        $marketId = $this->safe_string($trade, 'product_id');
        $messageHash = 'market_trades::' . $marketId;
        $symbol = $this->safe_symbol($marketId);
        $tradesArray = $this->safe_value($this->trades, $symbol);
        if ($tradesArray === null) {
            $tradesLimit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $tradesArray = new ArrayCacheBySymbolById ($tradesLimit);
            $this->trades[$symbol] = $tradesArray;
        }
        for ($i = 0; $i < count($events); $i++) {
            $currentEvent = $events[$i];
            $currentTrades = $this->safe_value($currentEvent, 'trades');
            for ($j = 0; $j < count($currentTrades); $j++) {
                $item = $currentTrades[$i];
                $tradesArray->append ($this->parse_trade($item));
            }
        }
        $client->resolve ($tradesArray, $messageHash);
        if (str_ends_with($marketId, 'USD')) {
            $client->resolve ($tradesArray, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
        }
        return $message;
    }

    public function handle_order($client, $message) {
        //
        //    {
        //        "channel" => "user",
        //        "client_id" => "",
        //        "timestamp" => "2023-02-09T20:33:57.609931463Z",
        //        "sequence_num" => 0,
        //        "events" => array(
        //            {
        //                "type" => "snapshot",
        //                "orders" => array(
        //                    array(
        //                        "order_id" => "XXX",
        //                        "client_order_id" => "YYY",
        //                        "cumulative_quantity" => "0",
        //                        "leaves_quantity" => "0.000994",
        //                        "avg_price" => "0",
        //                        "total_fees" => "0",
        //                        "status" => "OPEN",
        //                        "product_id" => "BTC-USD",
        //                        "creation_time" => "2022-12-07T19:42:18.719312Z",
        //                        "order_side" => "BUY",
        //                        "order_type" => "Limit"
        //                    ),
        //                )
        //            }
        //        )
        //    }
        //
        $events = $this->safe_value($message, 'events');
        $marketIds = array();
        if ($this->orders === null) {
            $limit = $this->safe_integer($this->options, 'ordersLimit', 1000);
            $this->orders = new ArrayCacheBySymbolById ($limit);
        }
        for ($i = 0; $i < count($events); $i++) {
            $event = $events[$i];
            $responseOrders = $this->safe_value($event, 'orders');
            for ($j = 0; $j < count($responseOrders); $j++) {
                $responseOrder = $responseOrders[$j];
                $parsed = $this->parse_ws_order($responseOrder);
                $cachedOrders = $this->orders;
                $marketId = $this->safe_string($responseOrder, 'product_id');
                if (!(is_array($marketIds) && array_key_exists($marketId, $marketIds))) {
                    $marketIds[] = $marketId;
                }
                $cachedOrders->append ($parsed);
            }
        }
        for ($i = 0; $i < count($marketIds); $i++) {
            $marketId = $marketIds[$i];
            $messageHash = 'user::' . $marketId;
            $client->resolve ($this->orders, $messageHash);
            if (str_ends_with($messageHash, 'USD')) {
                $client->resolve ($this->orders, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
            }
        }
        $client->resolve ($this->orders, 'user');
        return $message;
    }

    public function parse_ws_order($order, $market = null) {
        //
        //    {
        //        "order_id" => "XXX",
        //        "client_order_id" => "YYY",
        //        "cumulative_quantity" => "0",
        //        "leaves_quantity" => "0.000994",
        //        "avg_price" => "0",
        //        "total_fees" => "0",
        //        "status" => "OPEN",
        //        "product_id" => "BTC-USD",
        //        "creation_time" => "2022-12-07T19:42:18.719312Z",
        //        "order_side" => "BUY",
        //        "order_type" => "Limit"
        //    }
        //
        $id = $this->safe_string($order, 'order_id');
        $clientOrderId = $this->safe_string($order, 'client_order_id');
        $marketId = $this->safe_string($order, 'product_id');
        $datetime = $this->safe_string($order, 'time');
        $market = $this->safe_market($marketId, $market);
        return $this->safe_order(array(
            'info' => $order,
            'symbol' => $this->safe_string($market, 'symbol'),
            'id' => $id,
            'clientOrderId' => $clientOrderId,
            'timestamp' => $this->parse8601($datetime),
            'datetime' => $datetime,
            'lastTradeTimestamp' => null,
            'type' => $this->safe_string($order, 'order_type'),
            'timeInForce' => null,
            'postOnly' => null,
            'side' => $this->safe_string($order, 'side'),
            'price' => null,
            'stopPrice' => null,
            'triggerPrice' => null,
            'amount' => null,
            'cost' => null,
            'average' => $this->safe_string($order, 'avg_price'),
            'filled' => $this->safe_string($order, 'cumulative_quantity'),
            'remaining' => $this->safe_string($order, 'leaves_quantity'),
            'status' => $this->safe_string_lower($order, 'status'),
            'fee' => array(
                'amount' => $this->safe_string($order, 'total_fees'),
                'currency' => $this->safe_string($market, 'quote'),
            ),
            'trades' => null,
        ));
    }

    public function handle_order_book_helper($orderbook, $updates) {
        for ($i = 0; $i < count($updates); $i++) {
            $trade = $updates[$i];
            $sideId = $this->safe_string($trade, 'side');
            $side = $this->safe_string($this->options['sides'], $sideId);
            $price = $this->safe_number($trade, 'price_level');
            $amount = $this->safe_number($trade, 'new_quantity');
            $orderbookSide = $orderbook[$side];
            $orderbookSide->store ($price, $amount);
        }
    }

    public function handle_order_book($client, $message) {
        //
        //    {
        //        "channel" => "l2_data",
        //        "client_id" => "",
        //        "timestamp" => "2023-02-09T20:32:50.714964855Z",
        //        "sequence_num" => 0,
        //        "events" => array(
        //            {
        //                "type" => "snapshot",
        //                "product_id" => "BTC-USD",
        //                "updates" => array(
        //                    array(
        //                        "side" => "bid",
        //                        "event_time" => "1970-01-01T00:00:00Z",
        //                        "price_level" => "21921.73",
        //                        "new_quantity" => "0.06317902"
        //                    ),
        //                    array(
        //                        "side" => "bid",
        //                        "event_time" => "1970-01-01T00:00:00Z",
        //                        "price_level" => "21921.3",
        //                        "new_quantity" => "0.02"
        //                    ),
        //                )
        //            }
        //        )
        //    }
        //
        $events = $this->safe_value($message, 'events');
        $datetime = $this->safe_string($message, 'timestamp');
        for ($i = 0; $i < count($events); $i++) {
            $event = $events[$i];
            $updates = $this->safe_value($event, 'updates', array());
            $marketId = $this->safe_string($event, 'product_id');
            $messageHash = 'level2::' . $marketId;
            $subscription = $this->safe_value($client->subscriptions, $messageHash, array());
            $limit = $this->safe_integer($subscription, 'limit');
            $symbol = $this->safe_symbol($marketId);
            $type = $this->safe_string($event, 'type');
            if ($type === 'snapshot') {
                $this->orderbooks[$symbol] = $this->order_book(array(), $limit);
                $orderbook = $this->orderbooks[$symbol];
                $this->handle_order_book_helper($orderbook, $updates);
                $orderbook['timestamp'] = null;
                $orderbook['datetime'] = null;
                $orderbook['symbol'] = $symbol;
                $client->resolve ($orderbook, $messageHash);
                if (str_ends_with($messageHash, 'USD')) {
                    $client->resolve ($orderbook, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
                }
            } elseif ($type === 'update') {
                $orderbook = $this->orderbooks[$symbol];
                $this->handle_order_book_helper($orderbook, $updates);
                $orderbook['datetime'] = $datetime;
                $orderbook['timestamp'] = $this->parse8601($datetime);
                $orderbook['symbol'] = $symbol;
                $client->resolve ($orderbook, $messageHash);
                if (str_ends_with($messageHash, 'USD')) {
                    $client->resolve ($orderbook, $messageHash . 'C'); // sometimes we subscribe to BTC/USDC and coinbase returns BTC/USD
                }
            }
        }
        return $message;
    }

    public function handle_subscription_status($client, $message) {
        //
        //     {
        //         "type" => "subscriptions",
        //         "channels" => array(
        //             {
        //                 "name" => "level2",
        //                 "product_ids" => array( "ETH-BTC" )
        //             }
        //         )
        //     }
        //
        return $message;
    }

    public function handle_message($client, $message) {
        $channel = $this->safe_string($message, 'channel');
        $methods = array(
            'subscriptions' => array($this, 'handle_subscription_status'),
            'ticker' => array($this, 'handle_tickers'),
            'ticker_batch' => array($this, 'handle_tickers'),
            'market_trades' => array($this, 'handle_trade'),
            'user' => array($this, 'handle_order'),
            'l2_data' => array($this, 'handle_order_book'),
        );
        $type = $this->safe_string($message, 'type');
        if ($type === 'error') {
            $errorMessage = $this->safe_string($message, 'message');
            throw new ExchangeError($errorMessage);
        }
        $method = $this->safe_value($methods, $channel);
        $method($client, $message);
    }
}
