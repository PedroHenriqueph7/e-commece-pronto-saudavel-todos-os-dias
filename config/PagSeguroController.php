<?php
// config/PagSeguroController.php
// Compatível com Checkout Moderno PagBank (checkout/v1)

class PagSeguroController {
    private $baseUrl;
    private $apiKey;
    private $returnUrl;
    private $notificationUrl;

    public function __construct(array $config) {
        $env = $config['environment'] ?? 'sandbox';

        $this->baseUrl         = rtrim($config[$env]['base_url'] ?? '', '/');
        $this->apiKey          = $config[$env]['api_key'] ?? '';
        $this->returnUrl       = $config['return_url'] ?? '';
        $this->notificationUrl = $config['notification_url'] ?? '';
    }

    /**
     * Cria um checkout e retorna a URL para redirecionamento
     * $order: ['id'=>..., 'items'=>[...], 'payer'=>[...] ]
     */
    public function createCheckout(array $order) {

        // Endpoint com versão (compatível com muitas contas)
        $endpoint = $this->baseUrl . '/checkout/v1/checkouts';

        $payload = $this->buildPayload($order);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $errno  = curl_errno($ch);
        $err    = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception("Erro de conexão ao PagBank: $err");
        }

        $data = json_decode($resp, true);

        if ($status >= 400 || $data === null) {
            throw new Exception("Erro PagBank (HTTP {$status}) - resposta: " . substr($resp, 0, 1500));
        }

        // Verifica vários formatos possíveis de retorno
        if (isset($data['checkout']['checkout_url'])) return $data['checkout']['checkout_url'];
        if (isset($data['checkout_url'])) return $data['checkout_url'];
        if (isset($data['payment_page']['url'])) return $data['payment_page']['url'];
        if (isset($data['url'])) return $data['url'];
        if (isset($data['redirect_url'])) return $data['redirect_url'];

        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $l) {
                if (!empty($l['href'])) return $l['href'];
            }
        }

        // fallback: retorna o objeto inteiro para debug (lançar exceção)
        throw new Exception("Formato de resposta inesperado: " . json_encode($data));
    }

    private function buildPayload(array $order) {

        $items = [];
        if (!empty($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as $i) {
                $items[] = [
                    "reference_id" => $i['sku'] ?? $i['id'] ?? '',
                    "name"         => $i['name'] ?? 'Item',
                    "description"  => $i['description'] ?? '',
                    "quantity"     => (int) ($i['quantity'] ?? 1),
                    // unit_amount em centavos
                    "unit_amount"  => (int) ($i['unit_amount'] ?? $i['price_cents'] ?? 0)
                ];
            }
        }

        $payload = [
            "reference_id"      => $order['id'] ?? uniqid("order_"),
            "items"             => $items,
            "redirect_url"      => $this->returnUrl,
            "notification_urls" => array_filter([$this->notificationUrl]) // array, PagBank espera lista
        ];

        if (!empty($order['payer'])) {
            // Alguns campos: name, email, phone
            $payload["customer"] = $order['payer'];
        }

        return $payload;
    }
}
