<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use \ccxtpro\ClientTrait; // websocket functionality
use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\NotSupported;

class binance extends \ccxt\binance {

    use ClientTrait;

    public function describe () {
        return array_replace_recursive(parent::describe (), array(
            'has' => array(
                'watchOrderBook' => true,
                'watchOHLCV' => true,
                'watchTrades' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://stream.binance.com:9443/ws',
                ),
            ),
            'options' => array(
                // 'marketsByLowercaseId' => array(),
                'subscriptions' => array(),
                'watchOrderBookRate' => 100, // get updates every 100ms or 1000ms
            ),
        ));
    }

    public function load_markets ($reload = false, $params = array ()) {
        $markets = parent::load_markets($reload, $params);
        $marketsByLowercaseId = $this->safe_value($this->options, 'marketsByLowercaseId');
        if (($marketsByLowercaseId === null) || $reload) {
            $marketsByLowercaseId = array();
            for ($i = 0; $i < count($this->symbols); $i++) {
                $symbol = $this->symbols[$i];
                $market = $this->markets[$symbol];
                $lowercaseId = $this->safe_string_lower($market, 'id');
                $market['lowercaseId'] = $lowercaseId;
                $this->markets_by_id[$market['id']] = $market;
                $this->markets[$symbol] = $market;
                $marketsByLowercaseId[$lowercaseId] = $this->markets[$symbol];
            }
            $this->options['marketsByLowercaseId'] = $marketsByLowercaseId;
        }
        return $markets;
    }

    public function watch_trades ($symbol) {
        //     $this->load_markets();
        //     $market = $this->market ($symbol);
        //     $url = $this->urls['api']['ws'] . strtolower($market['id']) . '@trade';
        //     return $this->WsTradesMessage ($url, $url);
        throw new NotSupported($this->id . ' watchTrades not implemented yet');
    }

    public function handle_trades ($response) {
        //     $parsed = $this->parse_trade($response);
        //     $parsed['symbol'] = $this->parseSymbol ($response);
        //     return $parsed;
        throw new NotSupported($this->id . ' handleTrades not implemented yet');
    }

    public function watch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        //     $this->load_markets();
        //     $interval = $this->timeframes[$timeframe];
        //     $market = $this->market ($symbol);
        //     $url = $this->urls['api']['ws'] . strtolower($market['id']) . '@kline_' . $interval;
        //     return $this->WsOHLCVMessage ($url, $url);
        throw new NotSupported($this->id . ' watchOHLCV not implemented yet');
    }

    public function handle_ohlcv ($ohlcv) {
        //     $data = $ohlcv['k'];
        //     $timestamp = $this->safe_integer($data, 'T');
        //     $open = $this->safe_float($data, 'o');
        //     $high = $this->safe_float($data, 'h');
        //     $close = $this->safe_float($data, 'l');
        //     $low = $this->safe_float($data, 'c');
        //     $volume = $this->safe_float($data, 'v');
        //     return [$timestamp, $open, $high, $close, $low, $volume];
        throw new NotSupported($this->id . ' handleOHLCV not implemented yet ' . $this->json ($ohlcv));
    }

    public function watch_order_book ($symbol, $limit = null, $params = array ()) {
        //
        // https://github.com/binance-exchange/binance-official-api-docs/blob/master/web-socket-streams.md#partial-book-depth-streams
        //
        // <$symbol>@depth<levels>@100ms or <$symbol>@depth<levels> (1000ms)
        // valid <levels> are 5, 10, or 20
        //
        if ($limit !== null) {
            if (($limit !== 5) && ($limit !== 10) && ($limit !== 20)) {
                throw new ExchangeError($this->id . ' watchOrderBook $limit argument must be null, 5, 10 or 20');
            }
        }
        $this->load_markets();
        $market = $this->market ($symbol);
        //
        // https://github.com/binance-exchange/binance-official-api-docs/blob/master/web-socket-streams.md#how-to-manage-a-local-order-book-correctly
        //
        // 1. Open a stream to wss://stream.binance.com:9443/ws/bnbbtc@depth.
        // 2. Buffer the events you receive from the stream.
        // 3. Get a depth snapshot from https://www.binance.com/api/v1/depth?$symbol=BNBBTC&$limit=1000 .
        // 4. Drop any event where u is <= lastUpdateId in the snapshot.
        // 5. The first processed event should have U <= lastUpdateId+1 AND u >= lastUpdateId+1.
        // 6. While listening to the stream, each new event's U should be equal to the previous event's u+1.
        // 7. The data in each event is the absolute quantity for a price level.
        // 8. If the quantity is 0, remove the price level.
        // 9. Receiving an event that removes a price level that is not in your local order book can happen and is normal.
        //
        $name = 'depth';
        $messageHash = $market['lowercaseId'] . '@' . $name;
        $url = $this->urls['api']['ws']; // . '/' . $messageHash;
        $requestId = $this->nonce ();
        $watchOrderBookRate = $this->safe_string($this->options, 'watchOrderBookRate', '100');
        $request = array(
            'method' => 'SUBSCRIBE',
            'params' => array(
                $messageHash . '@' . $watchOrderBookRate . 'ms',
            ),
            'id' => $requestId,
        );
        $subscription = array(
            'requestId' => (string) $requestId,
            'messageHash' => $messageHash,
            'name' => $name,
            'symbol' => $symbol,
            'method' => array($this, 'handle_order_book_subscription'),
        );
        $message = array_merge($request, $params);
        // 1. Open a stream to wss://stream.binance.com:9443/ws/bnbbtc@depth.
        $future = $this->watch ($url, $messageHash, $message, $messageHash, $subscription);
        return $this->after ($future, array($this, 'limit_order_book'), $symbol, $limit, $params);
    }

    public function limit_order_book ($orderbook, $symbol, $limit = null, $params = array ()) {
        return $orderbook->limit ($limit);
    }

    public function fetch_order_book_snapshot ($client, $message, $subscription) {
        $symbol = $this->safe_string($subscription, 'symbol');
        $messageHash = $this->safe_string($subscription, 'messageHash');
        // 3. Get a depth $snapshot from https://www.binance.com/api/v1/depth?$symbol=BNBBTC&limit=1000 .
        // todo => this is a synch blocking call in ccxt.php - make it async
        $snapshot = $this->fetch_order_book($symbol);
        $orderbook = $this->orderbooks[$symbol];
        $orderbook->reset ($snapshot);
        // unroll the accumulated deltas
        $messages = $orderbook->cache;
        for ($i = 0; $i < count($messages); $i++) {
            $message = $messages[$i];
            $this->handle_order_book_message ($client, $message, $orderbook);
        }
        $this->orderbooks[$symbol] = $orderbook;
        $client->resolve ($orderbook, $messageHash);
    }

    public function handle_delta ($bookside, $delta) {
        $price = $this->safe_float($delta, 0);
        $amount = $this->safe_float($delta, 1);
        $bookside->store ($price, $amount);
    }

    public function handle_deltas ($bookside, $deltas) {
        for ($i = 0; $i < count($deltas); $i++) {
            $this->handle_delta ($bookside, $deltas[$i]);
        }
    }

    public function handle_order_book_message ($client, $message, $orderbook) {
        $u = $this->safe_integer_2($message, 'u', 'lastUpdateId');
        // merge accumulated deltas
        // 4. Drop any event where $u is <= lastUpdateId in the snapshot.
        if ($u > $orderbook['nonce']) {
            $U = $this->safe_integer($message, 'U');
            // 5. The first processed event should have $U <= lastUpdateId+1 AND $u >= lastUpdateId+1.
            if (($U !== null) && (($U - 1) > $orderbook['nonce'])) {
                throw new ExchangeError($this->id . ' handleOrderBook received an out-of-order nonce');
            }
            $this->handle_deltas ($orderbook['asks'], $this->safe_value($message, 'a', array()));
            $this->handle_deltas ($orderbook['bids'], $this->safe_value($message, 'b', array()));
            $orderbook['nonce'] = $u;
            $timestamp = $this->safe_integer($message, 'E');
            $orderbook['timestamp'] = $timestamp;
            $orderbook['datetime'] = $this->iso8601 ($timestamp);
        }
        return $orderbook;
    }

    public function handle_order_book ($client, $message) {
        //
        // initial snapshot is fetched with ccxt's fetchOrderBook
        // the feed does not include a snapshot, just the deltas
        //
        //     {
        //         "e" => "depthUpdate", // Event type
        //         "E" => 1577554482280, // Event time
        //         "s" => "BNBBTC", // Symbol
        //         "U" => 157, // First update ID in event
        //         "u" => 160, // Final update ID in event
        //         "b" => array( // bids
        //             array( "0.0024", "10" ), // price, size
        //         ),
        //         "a" => array( // asks
        //             array( "0.0026", "100" ), // price, size
        //         )
        //     }
        //
        $marketId = $this->safe_string($message, 's');
        $market = null;
        $symbol = null;
        if ($marketId !== null) {
            if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
                $market = $this->markets_by_id[$marketId];
                $symbol = $market['symbol'];
            }
        }
        $name = 'depth';
        $messageHash = $market['lowercaseId'] . '@' . $name;
        $orderbook = $this->orderbooks[$symbol];
        if ($orderbook['nonce'] !== null) {
            // 5. The first processed event should have U <= lastUpdateId+1 AND u >= lastUpdateId+1.
            // 6. While listening to the stream, each new event's U should be equal to the previous event's u+1.
            $this->handle_order_book_message ($client, $message, $orderbook);
            $client->resolve ($orderbook, $messageHash);
        } else {
            // 2. Buffer the events you receive from the stream.
            $orderbook->cache[] = $message;
        }
    }

    public function sign_message ($client, $messageHash, $message, $params = array ()) {
        // todo => binance signMessage not implemented yet
        return $message;
    }

    public function handle_order_book_subscription ($client, $message, $subscription) {
        $symbol = $this->safe_string($subscription, 'symbol');
        if (is_array($this->orderbooks) && array_key_exists($symbol, $this->orderbooks)) {
            unset($this->orderbooks[$symbol]);
        }
        $this->orderbooks[$symbol] = $this->limited_order_book();
        // fetch the snapshot in a separate async call
        $this->spawn (array($this, 'fetch_order_book_snapshot'), $client, $message, $subscription);
    }

    public function handle_subscription_status ($client, $message) {
        //
        //     {
        //         "result" => null,
        //         "id" => 1574649734450
        //     }
        //
        $requestId = $this->safe_string($message, 'id');
        $subscriptionsByRequestId = $this->index_by($client->subscriptions, 'requestId');
        $subscription = $this->safe_value($subscriptionsByRequestId, $requestId, array());
        $method = $this->safe_value($subscription, 'method');
        if ($method !== null) {
            $this->call ($method, $client, $message, $subscription);
        }
        return $message;
    }

    public function handle_message ($client, $message) {
        $methods = array(
            'depthUpdate' => array($this, 'handle_order_book'),
        );
        $event = $this->safe_string($message, 'e');
        $method = $this->safe_value($methods, $event);
        if ($method === null) {
            $requestId = $this->safe_string($message, 'id');
            if ($requestId !== null) {
                return $this->handle_subscription_status ($client, $message);
            }
            return $message;
        } else {
            return $this->call ($method, $client, $message);
        }
        //
        // --------------------------------------------------------------------
        //
        // var_dump (new Date (), json_encode ($message, null, 4));
        // var_dump ('---------------------------------------------------------');
        // if (gettype($message) === 'array' && count(array_filter(array_keys($message), 'is_string')) == 0) {
        //     $channelId = (string) $message[0];
        //     $subscriptionStatus = $this->safe_value($this->options['subscriptionStatusByChannelId'], $channelId, array());
        //     $subscription = $this->safe_value($subscriptionStatus, 'subscription', array());
        //     $name = $this->safe_string($subscription, 'name');
        //     $methods = array(
        //         'book' => 'handleOrderBook',
        //         'ohlc' => 'handleOHLCV',
        //         'ticker' => 'handleTicker',
        //         'trade' => 'handleTrades',
        //     );
        //     $method = $this->safe_string($methods, $name);
        //     if ($method === null) {
        //         return $message;
        //     } else {
        //         return $this->$method ($client, $message);
        //     }
        // } else {
        //     if ($this->handleErrorMessage ($client, $message)) {
        //         $event = $this->safe_string($message, 'event');
        //         $methods = array(
        //             'heartbeat' => 'handleHeartbeat',
        //             'systemStatus' => 'handleSystemStatus',
        //             'subscriptionStatus' => 'handleSubscriptionStatus',
        //         );
        //         $method = $this->safe_string($methods, $event);
        //         if ($method === null) {
        //             return $message;
        //         } else {
        //             return $this->$method ($client, $message);
        //         }
        //     }
        // }
    }
}
