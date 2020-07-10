<?php

namespace Ptchr\PostalCodeService\Tests;

use PHPUnit\Framework\TestCase;
use Ptchr\PostalCodeService\Pro6pp;

class Pro6ppTest extends TestCase
{
    /**
     * @var Pro6pp
     */
    private $pro66p;

    public function setUp(): void
    {
        parent::setUp();
        $this->pro66p = new Pro6pp($_SERVER['API_KEY']);
    }

    public function test_autocomplete()
    {
        $data = $this->pro66p->autocomplete('5212 VJ', 2);
        $this->assertNotNull($data);

        $data = $this->pro66p->autocomplete('1050', 86, null, 'Chaussee de Boondael');
        $this->assertNotNull($data);

        $data = $this->pro66p->autocomplete('80935', 22, 'München', 'Freudstraße');
        $this->assertNotNull($data);
    }

    /** @test * */
    public function suggesting_addresses_based_on_zipcode()
    {
        $addresses = [
            [
                'country' => 'nl',
                'max_results' => 10,
                'postal_code' => '5212',
            ],
            [
                'country' => 'nl',
                'max_results' => 20,
                'postal_code' => '5212',
            ],
        ];

        foreach ($addresses as $address) {
            $suggest = $this->pro66p->suggestAddressesByPostalCode(
                $address['country'],
                $address['postal_code'],
                $address['max_results']
            );

            $this->assertCount($address['max_results'], $suggest);
        }
    }

    /** @test * */
    public function suggesting_cities_based_on_city_name()
    {
        $cities = [
            [
                'country' => 'nl',
                'name' => 'breda',
                'max_results' => 100,
                'assert_count' => 1,
            ],
            [
                'country' => 'nl',
                'name' => 'bre',
                'max_results' => 100,
                'assert_count' => 8,
            ],
            [
                'country' => 'nl',
                'name' => 'bre',
                'max_results' => 1,
                'assert_count' => 1,
            ],
            [
                'country' => 'nl',
                'name' => 'sdgfsfdgsdfgfdsgdfsg',
                'max_results' => 100,
                'assert_count' => 0,
            ],
            [
                'country' => 'nl',
                'name' => 'sdgfsfdgsdfgfdsgdfsg',
                'max_results' => 100,
                'assert_count' => 0,
            ],
        ];

        foreach ($cities as $city) {
            $suggest = $this->pro66p->suggestCitiesByName(
                $city['country'],
                $city['name'],
                $city['max_results']
            );

            $this->assertCount($city['assert_count'], $suggest);
        }
    }
}
