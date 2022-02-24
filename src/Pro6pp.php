<?php

namespace Ptchr\PostalCodeService;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Ptchr\PostalCodeService\Exceptions\AddressNotFoundException;
use Ptchr\PostalCodeService\Exceptions\BadRequestException;
use Ptchr\PostalCodeService\Exceptions\InvalidPostalCodeException;
use Ptchr\PostalCodeService\Exceptions\UnauthorizedException;

class Pro6pp
{
    /**
     * @var Client
     */
    private Client $guzzle;

    /**
     * @var string
     */
    private string $baseUrl = 'https://api.pro6pp.nl/v2/';

    /**
     * Pro6pp constructor.
     * @param string $apiKey
     */
    public function __construct(private string $apiKey)
    {
        $this->guzzle = new Client();
    }

    /**
     * @param string $postalCode
     * @param string $houseNumber
     * @param string|null $city
     * @param string|null $street
     * @param string|null $addition
     * @return null|array
     * @throws AddressNotFoundException
     * @throws BadRequestException
     * @throws GuzzleException
     * @throws InvalidPostalCodeException
     * @throws UnauthorizedException
     */
    public function autocomplete(
        string $postalCode,
        string $houseNumber,
        string $city = null,
        string $street = null,
        string $addition = null
    ): ?array {
        $parameters = [];

        $parameters['streetNumber'] = str_replace(' ', '', $houseNumber);

        $postalCode = str_replace(' ', '', $postalCode);
        $postCodeContainsOnlyNumbers = (bool)preg_match('/^[0-9]+$/', $postalCode);


        switch (strlen($postalCode)) {
            case strlen($postalCode) === 6 && ! $postCodeContainsOnlyNumbers:
                $country = 'nl';
                $parameters['postalCode'] = $postalCode;

                break;
            case $city !== null && $street !== null && strlen($postalCode) === 5 && $postCodeContainsOnlyNumbers:
                $country = 'de';
                $parameters['postalCode'] = $postalCode;

                break;
            case $street !== null && strlen($postalCode) === 4 && $postCodeContainsOnlyNumbers:
                $country = 'be';
                $parameters['postalCode'] = $postalCode;

                break;
            default:
                throw new InvalidPostalCodeException('Cannot match Country postal code format for: ' . $postalCode);
        }

        if ($city !== null) {
            $parameters['settlement'] = $city;
        }

        if ($addition !== null) {
            $parameters['premise'] = $addition;
        }

        if ($street !== null) {
            $parameters['street'] = $street;
        }

        try {
            return $this->request('get', 'autocomplete/' . $country . '/', $parameters);
        } catch (ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            if ($statusCode === 404) {
                $message = 'No address found for postal code: ' . $postalCode . ' with house number: ' . $houseNumber;

                throw new AddressNotFoundException($message, (int)$exception->getCode(), $exception);
            }

            if ($statusCode === 400) {
                throw new BadRequestException('Invalid request', (int)$exception->getCode(), $exception);
            }

            if ($statusCode === 401) {
                throw new UnauthorizedException('Unauthorized', (int)$exception->getCode(), $exception);
            }

            throw new ClientException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception
            );
        }
    }

    /**
     * @param string $countryCode
     * @param string|int $postalCode
     * @param int $maxResults
     * @return array
     * @throws BadRequestException
     * @throws GuzzleException
     * @throws UnauthorizedException
     */
    public function suggestAddressesByPostalCode(string $countryCode, $postalCode, int $maxResults = 10): array
    {
        $parameters = [
            'postalCode' => $postalCode,
            'maxResults' => $maxResults,
        ];

        try {
            return $this->request('get', 'suggest/' . strtolower($countryCode) . '/postalCode', $parameters);
        } catch (ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            if ($statusCode === 400) {
                throw new BadRequestException('Invalid request', (int)$exception->getCode(), $exception);
            }

            if ($statusCode === 401) {
                throw new UnauthorizedException('Unauthorized', (int)$exception->getCode(), $exception);
            }

            throw new ClientException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception
            );
        }
    }

    /**
     * @param string $countryCode
     * @param string $cityName
     * @param int $maxResults
     * @return array
     * @throws BadRequestException
     * @throws GuzzleException
     * @throws UnauthorizedException
     */
    public function suggestCitiesByName(string $countryCode, string $cityName, int $maxResults = 10): ?array
    {
        $parameters = [
            'settlement' => $cityName,
            'maxResults' => $maxResults,
        ];

        try {
            return $this->request('get', 'suggest/' . strtolower($countryCode) . '/settlement', $parameters);
        } catch (ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            if ($statusCode === 400) {
                throw new BadRequestException('Invalid request', (int)$exception->getCode(), $exception);
            }

            if ($statusCode === 401) {
                throw new UnauthorizedException('Unauthorized', (int)$exception->getCode(), $exception);
            }

            throw new ClientException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception
            );
        }
    }

    /**
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float|int
     */
    public function distanceBetweenInKilometers(float $lat1, float $lon1, float $lat2, float $lon2): float|int
    {
        $pi80 = M_PI / 180;

        $lat1 *= $pi80;
        $lon1 *= $pi80;
        $lat2 *= $pi80;
        $lon2 *= $pi80;

        $r = 6372.797; // mean radius of Earth in km

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $requestParameters
     * @return array
     * @throws GuzzleException
     */
    private function request(string $method, string $endpoint, array $requestParameters): array
    {
        $requestParameters['authKey'] = $this->apiKey;

        $response = $this->guzzle->request($method, $this->baseUrl . $endpoint, [
            'query' => $requestParameters,
        ]);

        return $this->formatResponseData($response);
    }

    /**
     * @param ResponseInterface $response
     * @return array
     */
    private function formatResponseData(ResponseInterface $response): array
    {
        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }
}
