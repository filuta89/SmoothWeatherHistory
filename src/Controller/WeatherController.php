<?php

namespace App\Controller;

use App\Entity\WeatherData;
use App\Form\WeatherType;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WeatherController extends AbstractController
{
    /**
     * [Route('/weather', name: 'app_weather')]
     */
    public function index(): Response
    {
        return $this->render('weather/index.html.twig', [
            'controller_name' => 'WeatherController',
        ]);
    }

    /**
     * @Route("/fetch-weather", name="fetch_weather")
     */
    public function fetchWeatherAction(Request $request, EntityManagerInterface $em)
    {
        $form = $this->createForm(WeatherType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // code to fetch data from Open Meteo API and save to the database
            $client = new Client();

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

            //status code 200 = success
            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody(), true);

                if (isset($data['daily'])) {
                    foreach ($data['daily'] as $dailyData) {
                        $weatherData = new WeatherData();
                        $weatherData->setCity($form->get('city')->getData());
                        $weatherData->setLatitude($form->get('latitude')->getData());
                        $weatherData->setLongitude($form->get('longitude')->getData());

                        // Set the date
                        if (isset($dailyData['time'])) {
                            $date = new \DateTime('@' . $dailyData['time']);
                            $weatherData->setDate($date);
                        }

                        // Set temperature min and max
                        if (isset($dailyData['temperature_2m_min'])) {
                            $weatherData->setTemperatureMin($dailyData['temperature_2m_min']);
                        }
                        if (isset($dailyData['temperature_2m_max'])) {
                            $weatherData->setTemperatureMax($dailyData['temperature_2m_max']);
                        }

                        // Set precipitation
                        if (isset($dailyData['precipitation_sum'])) {
                            $weatherData->setPrecipitation($dailyData['precipitation_sum']);
                        }

                        $em->persist($weatherData);
                    }
                    $em->flush();
                }
            }
        }

        return $this->render('weather/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
