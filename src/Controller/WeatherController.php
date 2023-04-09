<?php

namespace App\Controller;

use App\Entity\ResponseCommonData;
use App\Entity\WeatherData;
use App\Form\WeatherType;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class WeatherController extends AbstractController
{
    private function initializeSession(SessionInterface $session, EntityManagerInterface $em): string
    {
        if (!$session->isStarted()) {
            $session->start();
            $this->deleteExpiredResponsesAction($em);
        }
        return $session->getId();
    }

    /**
     * @Route("/", name="fetch_weather", methods={"GET", "POST"})
     */
    public function fetchWeatherAction(Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $sessionId = $this->initializeSession($session, $em);
        $responseId = 0;

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
                    'daily' => 'temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_sum,windspeed_10m_max,windgusts_10m_max',
                    'timezone' => 'Europe/Berlin'
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody(), true);
                //file_put_contents('response_data.json', json_encode($data, JSON_PRETTY_PRINT));   // save json to file

                if (isset($data['daily'])) {
                    $dates = $data['daily']['time'];
                    $temperatureMax = $data['daily']['temperature_2m_max'];
                    $temperatureMin = $data['daily']['temperature_2m_min'];
                    $temperatureMean = $data['daily']['temperature_2m_mean'];
                    $precipitation = $data['daily']['precipitation_sum'];
                    $windSpeedMax = $data['daily']['windspeed_10m_max'];
                    $windGustsMax = $data['daily']['windgusts_10m_max'];

                    $responseCommonData = new ResponseCommonData();
                    $responseCommonData->setSessionId($sessionId);
                    $responseCommonData->setCity($form->get('city')->getData());
                    $responseCommonData->setLatitude($form->get('latitude')->getData());
                    $responseCommonData->setLongitude($form->get('longitude')->getData());
                    $responseCommonData->setLastActivity(new \DateTime());

                    $em->persist($responseCommonData);
                    $em->flush();

                    $responseId = $responseCommonData->getId();

                    $numDays = count($dates);

                    for ($i = 0; $i < $numDays; $i++) {
                        $weatherData = new WeatherData();
                        $weatherData->setResponseId($responseId);

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

                        if (isset($temperatureMean[$i])) {
                            $weatherData->setTemperatureMean($temperatureMean[$i]);
                        }

                        if (isset($precipitation[$i])) {
                            $weatherData->setPrecipitation($precipitation[$i]);
                        }

                        if (isset($windSpeedMax[$i])) {
                            $weatherData->setWindSpeedMax($windSpeedMax[$i]);
                        }

                        if (isset($windGustsMax[$i])) {
                            $weatherData->setWindGustsMax($windGustsMax[$i]);
                        }


                        $em->persist($weatherData);
                    }
                    $em->flush();
                }
            }
            $em->flush();

            $responseIds = $em->getRepository(ResponseCommonData::class)->findBy(['sessionId' => $sessionId], ['id' => 'DESC']);

            $weather_data = [];
            foreach ($responseIds as $responseIdEntity) {
                $weather_data[] = $this->getWeatherData($em, $responseIdEntity->getId());
            }
        } else {
            $responseIds = $em->getRepository(ResponseCommonData::class)->findBy(['sessionId' => $sessionId], ['id' => 'DESC']);

            $weather_data = [];
            foreach ($responseIds as $responseIdEntity) {
                $weather_data[] = $this->getWeatherData($em, $responseIdEntity->getId());
            }
        }

        return $this->render('weather/index.html.twig', [
            'form' => $form->createView(),
            'weather_data' => $weather_data,
            'response_id' => $responseId,
        ]);

    }

    private function getWeatherData(EntityManagerInterface $em, int $responseId): array
    {
        $weatherData = $em->getRepository(WeatherData::class)->findBy(['responseId' => $responseId]);

        $data = [];

        foreach ($weatherData as $weather) {
            $dailyData = [
                //'id' => $weather->getId(),
                'date' => $weather->getDate(),
                'temperature_min' => $weather->getTemperatureMin(),
                'temperature_max' => $weather->getTemperatureMax(),
                'temperature_mean' => $weather->getTemperatureMean(),
                'precipitation' => $weather->getPrecipitation(),
                'wind_speed_max' => $weather->getWindSpeedMax(),
                'wind_gusts_max' => $weather->getWindGustsMax(),
            ];

            $identifier = $weather->getResponseId();
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

            $data[$identifier]['response_id'] = $responseId;
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
        $responseId = $request->request->get('response_id');

        if ($responseId) {
            $weatherDataRepo = $em->getRepository(WeatherData::class);
            $weatherDataList = $weatherDataRepo->findBy(['response_id' => (int)$responseId]);

            foreach ($weatherDataList as $weatherData) {
                $em->remove($weatherData);
            }
            $em->flush();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/delete-expired-session-data", name="delete_expired_session_data", methods={"GET"})
     */
    public function deleteExpiredResponsesAction(EntityManagerInterface $em): Response
    {
        $responseCommonDataRepo = $em->getRepository(ResponseCommonData::class);
        $weatherDataRepo = $em->getRepository(WeatherData::class);
        $expiredResponsesIds = $responseCommonDataRepo->findExpiredSessions();

        if (!empty($expiredResponsesIds)) {
            foreach ($expiredResponsesIds as $responseId) {
                $expiredWeatherData = $weatherDataRepo->findBy(['responseId' => $responseId]);

                if ($expiredWeatherData) {
                    foreach ($expiredWeatherData as $data) {
                        $em->remove($data);
                    }
                }
            }

            $em->flush();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

}
