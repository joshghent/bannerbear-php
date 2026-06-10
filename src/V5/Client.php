<?php

namespace Bannerbear\V5;


class Client
{
    /** @var string */
    private $apiKey;
    /** @var string */
    protected static $apiBase = 'https://api.bannerbear.com/v5';
    /** @var string */
    protected static $syncApiBase = 'https://sync.api.bannerbear.com/v5';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ? $apiKey : (string)getenv('BANNERBEAR_API_KEY');
    }

    protected function factory(): Api
    {
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        return new Api(self::$apiBase, $headers);
    }

    protected function factorySync(): Api
    {
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        return new Api(self::$syncApiBase, $headers);
    }

    public function account()
    {
        return $this->factory()->get('/account');
    }

    // =================================
    //         IMAGE TEMPLATES
    // =================================

    public function list_image_templates(?int $page = null)
    {
        $qs = $page ? '?page=' . $page : '';
        return $this->factory()->get('/image_templates' . $qs);
    }

    public function get_image_template(string $uid)
    {
        return $this->factory()->get('/image_templates/' . $uid);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function update_image_template(string $uid, array $params)
    {
        return $this->factory()->patch('/image_templates/' . $uid, $params);
    }

    // =================================
    //              IMAGES
    // =================================

    public function get_image(string $uid)
    {
        return $this->factory()->get('/images/' . $uid);
    }

    public function list_images(?int $page = null)
    {
        $qs = $page ? '?page=' . $page : '';
        return $this->factory()->get('/images' . $qs);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function create_image(string $uid, array $params, bool $synchronous = false)
    {
        $params['template'] = $uid;
        if ($synchronous) {
            return $this->factorySync()->post('/images', $params);
        }
        return $this->factory()->post('/images', $params);
    }

    // =================================
    //             BATCHES
    // =================================

    public function list_batches(?int $page = null)
    {
        $qs = $page ? '?page=' . $page : '';
        return $this->factory()->get('/batches' . $qs);
    }

    public function get_batch(string $uid)
    {
        return $this->factory()->get('/batches/' . $uid);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function create_batch(array $params)
    {
        return $this->factory()->post('/batches', $params);
    }

    // =================================
    //            WEBHOOKS
    // =================================

    public function list_webhooks(?int $page = null)
    {
        $qs = $page ? '?page=' . $page : '';
        return $this->factory()->get('/webhooks' . $qs);
    }

    public function get_webhook(string $uid)
    {
        return $this->factory()->get('/webhooks/' . $uid);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function create_webhook(array $params)
    {
        return $this->factory()->post('/webhooks', $params);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function update_webhook(string $uid, array $params)
    {
        return $this->factory()->patch('/webhooks/' . $uid, $params);
    }

    public function delete_webhook(string $uid)
    {
        return $this->factory()->delete('/webhooks/' . $uid);
    }

    // =================================
    //          INSTANT URLS
    // =================================

    public function list_instant_urls(?int $page = null)
    {
        $qs = $page ? '?page=' . $page : '';
        return $this->factory()->get('/instant_urls' . $qs);
    }

    public function get_instant_url(string $uid)
    {
        return $this->factory()->get('/instant_urls/' . $uid);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function create_instant_url(array $params)
    {
        return $this->factory()->post('/instant_urls', $params);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function update_instant_url(string $uid, array $params)
    {
        return $this->factory()->patch('/instant_urls/' . $uid, $params);
    }

    public function delete_instant_url(string $uid)
    {
        return $this->factory()->delete('/instant_urls/' . $uid);
    }

    /**
     * Pure local helper — no API call. Builds an instant URL from a base + modifications,
     * optionally appending an HMAC-SHA256 signature when a signing_key is provided.
     *
     * @param array{mode?: string, signing_key?: string, modifications: array<string,mixed>} $params
     */
    public function build_instant_url(string $baseUrl, array $params): string
    {
        $mode = $params['mode'] ?? 'encoded';
        $signingKey = $params['signing_key'] ?? null;
        $modifications = $params['modifications'] ?? [];

        if ($mode === 'encoded') {
            // If only `objects` is present, encode just the array (matches server canonical form).
            // Otherwise encode the full object with `template` key before `objects`.
            $template = $modifications['template'] ?? null;
            $objects  = $modifications['objects']  ?? null;
            if ($template === null) {
                $data = $objects ?? [];
            } else {
                $data = ['template' => $template];
                if ($objects !== null) {
                    $data['objects'] = $objects;
                }
            }
            $json = json_encode($data);
            if (!is_string($json)) {
                throw new \Exception('failed to encode modifications');
            }
            $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
            $url = $baseUrl . '?modifications=' . $encoded;
        } elseif ($mode === 'named_params') {
            $parts = [];
            $template = $modifications['template'] ?? null;
            if (is_array($template)) {
                foreach ($template as $k => $v) {
                    $parts[] = 'template:' . $k . '=' . urlencode((string)$v);
                }
            }
            $objects = $modifications['objects'] ?? [];
            if (is_array($objects)) {
                foreach ($objects as $obj) {
                    $name = $obj['name'] ?? '';
                    foreach ($obj as $k => $v) {
                        if ($k === 'name') continue;
                        $parts[] = $name . ':' . $k . '=' . urlencode((string)$v);
                    }
                }
            }
            $url = $baseUrl . '?' . implode('&', $parts);
        } else {
            throw new \Exception('unknown instant URL mode: ' . $mode);
        }

        if (!$signingKey) {
            return $url;
        }
        $sig = hash_hmac('sha256', $url, $signingKey);
        return $url . '&s=' . $sig;
    }
}


class Api
{
    /** @var \CurlHandle|resource */
    private $client;
    /** @var string */
    protected $url;

    /**
     * @param array<int,string> $headers
     */
    public function __construct(string $url, array $headers = [])
    {
        $curlClient = curl_init();
        curl_setopt_array($curlClient, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => true,
        ]);
        $this->client = $curlClient;
        $this->url = $url;
    }

    private function getUrl(string $url): string
    {
        return $this->url . $url;
    }

    public function get(string $url)
    {
        curl_setopt($this->client, CURLOPT_URL, $this->getUrl($url));
        $res = curl_exec($this->client);
        $this->checkAndClose();
        return is_string($res) && $res !== '' ? json_decode($res, true) : null;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function patch(string $url, array $params)
    {
        curl_setopt($this->client, CURLOPT_URL, $this->getUrl($url));
        curl_setopt($this->client, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($this->client, CURLOPT_POSTFIELDS, json_encode($params));
        $res = curl_exec($this->client);
        $this->checkAndClose();
        return is_string($res) && $res !== '' ? json_decode($res, true) : null;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function post(string $url, array $params)
    {
        curl_setopt($this->client, CURLOPT_URL, $this->getUrl($url));
        curl_setopt($this->client, CURLOPT_POST, true);
        curl_setopt($this->client, CURLOPT_POSTFIELDS, json_encode($params));
        $res = curl_exec($this->client);
        $this->checkAndClose();
        return is_string($res) && $res !== '' ? json_decode($res, true) : null;
    }

    public function delete(string $url)
    {
        curl_setopt($this->client, CURLOPT_URL, $this->getUrl($url));
        curl_setopt($this->client, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $res = curl_exec($this->client);
        $this->checkAndClose();
        return is_string($res) && $res !== '' ? json_decode($res, true) : null;
    }

    private function checkAndClose(): void
    {
        $error_msg = null;
        $status = null;
        if (curl_errno($this->client)) {
            $error_msg = curl_error($this->client);
            $status = curl_getinfo($this->client, CURLINFO_RESPONSE_CODE);
        }
        curl_close($this->client);
        if ($error_msg !== null) {
            throw new \Exception('Bannerbear Error Status: ' . $status . '. Message: ' . $error_msg);
        }
    }
}
