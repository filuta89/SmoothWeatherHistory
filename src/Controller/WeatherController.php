<?php

namespace App\Controller;

use App\Entity\WeatherData;
use App\Form\WeatherType;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WeatherController extends AbstractController
{
    /**
     * @Route("/", name="fetch_weather", methods={"GET", "POST"})
     */
    public function fetchWeatherAction(Request $request, EntityManagerInterface $em)
    {
        $form = $this->createForm(WeatherType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // code to fetch data from Open-Meteo.com historical API and save to the database
            $client = new Client(['verify' => false]);

            $latitude = number_format($form->get('latitude')->getData(), 2);
            $longitude = number_format($form->get('longitude')->getData(), 2);

            $response = $client->get('https://archive-api.open-meteo.com/v1/archive', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'start_date' => $form->get('startDate')->getData()->format('Y-m-d'),
                    'end_date' => $form->get('endDate')->getData()->format('Y-m-d'),
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                    'timezone' => 'Europe/Berlin'
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody(), true);
//                file_put_contents('response_data.json', json_encode($data, JSON_PRETTY_PRINT));   // save json to file

                if (isset($data['daily'])) {
                    $dates = $data['daily']['time'];
                    $temperatureMin = $data['daily']['temperature_2m_min'];
                    $temperatureMax = $data['daily']['temperature_2m_max'];
                    $precipitation = $data['daily']['precipitation_sum'];

                    $numDays = count($dates);

                    for ($i = 0; $i < $numDays; $i++) {
                        $weatherData = new WeatherData();
                        $weatherData->setCity($form->get('city')->getData());
                        $weatherData->setLatitude($form->get('latitude')->getData());
                        $weatherData->setLongitude($form->get('longitude')->getData());

                        if (isset($dates[$i])) {
                            $date = new \DateTime($dates[$i]);
                            $weatherData->setDate($date);
                        }

                        if (isset($temperatureMin[$i])) {
                            $weatherData->setTemperatureMin($temperatureMin[$i]);
                        }

                        if (isset($temperatureMax[$i])) {
                            $weatherData->setTemperatureMax($temperatureMax[$i]);
                        }

                        if (isset($precipitation[$i])) {
                            $weatherData->setPrecipitation($precipitation[$i]);
                        }

                        $em->persist($weatherData);
                    }
                    $em->flush();
                }
            }
            $em->flush();
            $weather_data = $this->getWeatherData($em);
        } else {
            $weather_data = $this->getWeatherData($em);
        }

        return $this->render('weather/index.html.twig', [
            'form' => $form->createView(),
            'weather_data' => $weather_data,
        ]);

    }

    private function getWeatherData(EntityManagerInterface $em)
    {
        $weatherData = $em->getRepository(WeatherData::class)->findAll();

        $data = [];

        foreach ($weatherData as $weather) {
            $dailyData = [
                'id' => $weather->getId(),
                'date' => $weather->getDate(),
                'temperature_min' => $weather->getTemperatureMin(),
                'temperature_max' => $weather->getTemperatureMax(),
                'precipitation' => $weather->getPrecipitation(),
            ];

            $identifier = $weather->getCity() . $weather->getLatitude() . $weather->getLongitude();
            if (!isset($data[$identifier])) {
                $data[$identifier] = [
                    'startDate' => $weather->getDate(),
                    'endDate' => $weather->getDate(),
                    'average_temperature' => 0,
                    'total_precipitation' => 0,
                    'count' => 0,
                    'daily_data' => [],
                ];
            }

            $data[$identifier]['daily_data'][] = $dailyData;
            $data[$identifier]['average_temperature'] += ($dailyData['temperature_min'] + $dailyData['temperature_max']) / 2;
            $data[$identifier]['total_precipitation'] += $dailyData['precipitation'];
            $data[$identifier]['count']++;

            if ($weather->getDate() < $data[$identifier]['startDate']) {
                $data[$identifier]['startDate'] = $weather->getDate();
            }
            if ($weather->getDate() > $data[$identifier]['endDate']) {
                $data[$identifier]['endDate'] = $weather->getDate();
            }
        }

        foreach ($data as $key => $value) {
            $data[$key]['average_temperature'] /= $value['count'];
        }

        return array_values($data);
    }

    /**
     * @Route("/delete-weather-data", name="delete_weather_data", methods={"POST"})
     */
    public function deleteWeatherDataAction(Request $request, EntityManagerInterface $em): Response
    {
        $weatherIds = $request->request->get('weather_ids');

        if ($weatherIds) {
            $idsArray = explode(',', $weatherIds);
            $weatherDataRepo = $em->getRepository(WeatherData::class);

            foreach ($idsArray as $id) {
                $weatherData = $weatherDataRepo->find($id);
                if ($weatherData) {
                    $em->remove($weatherData);
                }
            }
            $em->flush();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

}
