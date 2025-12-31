<?php

namespace WeSellUltra\Import\Services;

use SoapClient;
use SoapFault;

class UltraClient
{
    private ?SoapClient $client = null;

    public function __construct(private readonly string $wsdl, private readonly array $options = [])
    {
    }

    public function requestData(string $service, bool $all = true, ?string $additionalParameters = null, bool $compress = false): string
    {
        $payload = [
            'Service' => $service,
            'all' => $all,
            'additionalParameters' => $additionalParameters,
            'compress' => $compress,
        ];

        $response = $this->client()->__soapCall('requestData', [$payload]);

        return (string)($response->return ?? $response);
    }

    public function isReady(string $id): bool
    {
        $response = $this->client()->__soapCall('isReady', [['ID' => $id]]);

        return (bool)($response->return ?? $response);
    }

    public function getDataById(string $id): array
    {
        $response = $this->client()->__soapCall('getDataByID', [['ID' => $id]]);

        return [
            'message' => (string)($response->message ?? ''),
            'data' => (string)($response->data ?? ''),
        ];
    }

    public function commitReceivingData(string $service): bool
    {
        $response = $this->client()->__soapCall('CommitReceivingData', [['Service' => $service]]);

        return (bool)($response->return ?? $response);
    }

    public function fetchData(string $service, bool $all, ?string $additionalParameters, bool $compress, int $maxAttempts, int $sleepSeconds): ?string
    {
        $id = $this->requestData($service, $all, $additionalParameters, $compress);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($this->isReady($id)) {
                $result = $this->getDataById($id);

                $message = strtoupper(trim($result['message']));
                $data = $result['data'];

                if ($message !== 'OK' && empty($data)) {
                    throw new SoapFault('Server', sprintf('Service %s failed with message: %s', $service, $result['message']));
                }

                return $data;
            }

            sleep($sleepSeconds);
        }

        throw new SoapFault('Server', sprintf('Service %s did not finish after %d attempts', $service, $maxAttempts));
    }

    private function client(): SoapClient
    {
        if ($this->client === null) {
            $this->client = new SoapClient($this->wsdl, $this->options);
        }

        return $this->client;
    }
}
