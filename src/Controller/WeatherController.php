<?php

namespace App\Controller;

use App\Entity\ResponseCommonData;
use App\Entity\WeatherData;
use App\Form\WeatherType;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
    public function fetchWeatherAction(Request $request, EntityManagerInterface $em, SessionInterface $session, LoggerInterface $logger): Response
    {
        $logger->info('fetchWeatherAction() called');

        try {
            $sessionId = $this->initializeSession($session, $em);

            try {
                $responseIds = $em->createQueryBuilder()
                    ->select('DISTINCT wd.responseId')
                    ->from(WeatherData::class, 'wd')
                    ->getQuery()
                    ->getArrayResult();

                $responseId = array_column($responseIds, 'responseId');

                $logger->info('----------------------------array of old responseIds------------------------>' . print_r($responseId, true));
//                var_dump($responseId);
            } catch (\Exception $e) {
                $error_message = 'There was an error executing the query built by the QueryBuilder. This might happen if the database connection is lost or if there is a syntax error in the SQL generated by the query builder: ' . $e->getMessage();
                $this->render('error.html.twig', ['error_message' => $error_message]);
            }

            $form = $this->createForm(WeatherType::class);
            $form->handleRequest($request);

            $newWeatherData = null;
            $newResponseId = null;

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $newResponseId[0] = $this->handleFormSubmission($form, $em, $sessionId);
                    $newWeatherData = $this->getWeatherDataForResponseIds($em, $newResponseId);
                    $logger->info('----------------------------$newResponseId------------------------>' . print_r($newResponseId[0], true));
//                    var_dump($newResponseId[0]);

                    $logger->info('----------------------------$newWeatherData------------------------>' . print_r($newWeatherData, true));
//                    var_dump($newWeatherData);

                } catch (\Exception $e) {
                    $error_message = 'An error occurred while displaying new response: ' . $e->getMessage();
                    $this->render('error.html.twig', ['error_message' => $error_message]);
                }
            }

            $weather_data = $this->getWeatherDataForResponseIds($em, $responseId);

            $logger->info('----------------------------$weather_data------------------------>' . print_r($weather_data, true));
//            var_dump($weather_data);

            if (isset($newResponseId)) {
                return $this->render('weather/index.html.twig', [
                    'form' => $form->createView(),
                    'weather_data' => $weather_data,
                    'response_ids' => $responseId,
                    'new_weather_data' => $newWeatherData,
                    'new_response_id' => $newResponseId[0],
                ]);
            } else {
                return $this->render('weather/index.html.twig', [
                    'form' => $form->createView(),
                    'weather_data' => $weather_data,
                    'response_ids' => $responseId,
                ]);
            }
        } catch (\Exception $e) {
            $error_message = 'An error occurred: ' . $e->getMessage();
            return $this->render('error.html.twig', ['error_message' => $error_message]);
        }
    }
    private function handleFormSubmission($form, EntityManagerInterface $em, string $sessionId): ?int
    {
        $data = $this->fetchWeatherDataFromAPI($form);

        if (isset($data['daily'])) {
            return $this->saveWeatherDataToDatabase($data, $form, $em, $sessionId);
        }
        return null;
    }

    private function fetchWeatherDataFromAPI($form): array
    {
        $this->get('logger')->info('fetchWeatherDataFromAPI() called');

        try {
            $client = new Client(['verify' => false]);

            $latitude = number_format($form->get('latitude')->getData(), 2);
            $longitude = number_format($form->get('longitude')->getData(), 2);

            $response = $client->get('http://archive-api.open-meteo.com/v1/archive', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'start_date' => $form->get('startDate')->getData()->format('Y-m-d'),
                    'end_date' => $form->get('endDate')->getData()->format('Y-m-d'),
                    'daily' => 'temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_sum,windspeed_10m_max,windgusts_10m_max',
                    'timezone' => 'Europe/Berlin'
                ]
            ]);

            return ($response->getStatusCode() == 200) ? json_decode($response->getBody(), true) : [];
        } catch (GuzzleException $e) {
            $error_message = 'There was a network/database connectivity issue or the API returned error response while fetching weather data from the API: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return [];
        } catch (\Exception $e) {
            $error_message = 'An unexpected error occurred while fetching weather data from the API: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return [];
        }
    }

    private function saveWeatherDataToDatabase(array $data, $form, EntityManagerInterface $em, string $sessionId): int
    {
        try {
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
            return $responseId;
        } catch (UniqueConstraintViolationException $e) {
            $error_message = 'Failed to save weather data to database. Failed to insert a duplicate value in a unique column of the database: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return -1;
        } catch (ORMException $e) {
            $error_message = 'Failed to save weather data to database. There was a general error with the entity manager or the database: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return -2;
        } catch (InvalidArgumentException $e) {
            $error_message = 'Failed to save weather data to database. There was an invalid argument passed to one of the functions: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return -3;
        } catch (\Exception $e) {
            $error_message = 'Failed to save weather data to database: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return -4;
        }
    }

    private function getWeatherDataForResponseIds(EntityManagerInterface $em, array $responseIds): array
    {
        $weather_data = [];
        foreach ($responseIds as $responseId) {
            try {
                $weather_data[] = $this->getWeatherData($em, $responseId);
            } catch (\Exception $e) {
                $error_message = 'An error occurred while getting weather data for response ID: ' . $e->getMessage();
                $this->render('error.html.twig', ['error_message' => $error_message]);
            }
        }

        return $weather_data;
    }

    public function getWeatherData(EntityManagerInterface $em, int $responseId): array
    {
        try {
            $weatherData = $em->getRepository(WeatherData::class)->findBy(['responseId' => $responseId]);
            $responseCommonData = $em->getRepository(ResponseCommonData::class)->find($responseId);

            $city = $responseCommonData ? (str_contains($responseCommonData->getCity(), ',') ? strstr($responseCommonData->getCity(), ',', true) : $responseCommonData->getCity()) : null;
            $cityFullName = $responseCommonData->getCity();

            $data = [];

            foreach ($weatherData as $weather) {
                $dailyData = [
                    'date' => $weather->getDate(),
                    'temperature_min' => $weather->getTemperatureMin(),
                    'temperature_max' => $weather->getTemperatureMax(),
                    'temperature_avg' => $weather->getTemperatureMean(),
                    'precipitation' => $weather->getPrecipitation(),
                    'wind_speed_max' => $weather->getWindSpeedMax(),
                    'wind_gusts_max' => $weather->getWindGustsMax(),
                ];

                $identifier = $weather->getResponseId();
                if (!isset($data[$identifier])) {
                    $data[$identifier] = [
                        'city' => $city,
                        'city_full_name' => $cityFullName,
                        'startDate' => $weather->getDate(),
                        'endDate' => $weather->getDate(),
                        'temp_avg_total' => 0,
                        'precipitation_total' => 0,
                        'temp_max_total' => PHP_INT_MIN,
                        'temp_min_total' => PHP_INT_MAX,
                        'count' => 0,
                        'daily_data' => [],
                    ];
                }

                $data[$identifier]['response_id'] = $identifier;
                $data[$identifier]['daily_data'][] = $dailyData;
                $data[$identifier]['temp_avg_total'] += ($dailyData['temperature_avg']);
                $data[$identifier]['precipitation_total'] += $dailyData['precipitation'];
                $data[$identifier]['count']++;

                $data[$identifier]['temp_max_total'] = max($data[$identifier]['temp_max_total'],
                    $dailyData['temperature_max']);
                $data[$identifier]['temp_min_total'] = min($data[$identifier]['temp_min_total'],
                    $dailyData['temperature_min']);

                if ($weather->getDate() < $data[$identifier]['startDate']) {
                    $data[$identifier]['startDate'] = $weather->getDate();
                }
                if ($weather->getDate() > $data[$identifier]['endDate']) {
                    $data[$identifier]['endDate'] = $weather->getDate();
                }
            }

            foreach ($data as $key => $value) {
                $data[$key]['temp_avg_total'] /= $value['count'];
                $data[$key]['temp_avg_total'] = round($data[$key]['temp_avg_total'], 2);
            }

            return array_values($data);
        } catch (\Exception $e) {
            $error_message = 'Error while trying to fetch data from database to view: ' . $e->getMessage();
            $this->render('error.html.twig', ['error_message' => $error_message]);
            return [];
        }
    }

    /**
     * @Route("/delete-weather-data", name="delete_weather_data", methods={"POST"})
     */
    public function deleteWeatherDataAction(Request $request, EntityManagerInterface $em): Response
    {
        $responseId = $request->request->get('response_id');

        if ($responseId) {
            $weatherDataRepo = $em->getRepository(WeatherData::class);
            $weatherDataList = $weatherDataRepo->findBy(['responseId' => (int)$responseId]);

            foreach ($weatherDataList as $weatherData) {
                $em->remove($weatherData);
            }

            $responseCommonDataRepo = $em->getRepository(ResponseCommonData::class);
            $responseCommonData = $responseCommonDataRepo->find((int)$responseId);

            if ($responseCommonData) {
                $em->remove($responseCommonData);
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
        $activeResponseIds = $responseCommonDataRepo->findActiveResponseIds();

        $weatherDataQueryBuilder = $weatherDataRepo->createQueryBuilder('wd')
            ->delete();
        if (!empty($activeResponseIds)) {
            $weatherDataQueryBuilder
                ->where('wd.responseId NOT IN (:activeResponseIds)')
                ->setParameter('activeResponseIds', $activeResponseIds);
        }
        $weatherDataQueryBuilder
            ->orWhere('wd.responseId IS NULL')
            ->getQuery()
            ->execute();

        if (!empty($activeResponseIds)) {
            $responseCommonDataRepo->createQueryBuilder('rcd')
                ->delete()
                ->where('rcd.id NOT IN (:activeResponseIds)')
                ->setParameter('activeResponseIds', $activeResponseIds)
                ->getQuery()
                ->execute();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

}
