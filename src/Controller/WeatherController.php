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

            //status code 200 = success
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

                        // Set the date
                        if (isset($dates[$i])) {
                            $date = new \DateTime($dates[$i]);
                            $weatherData->setDate($date);
                        }

                        // Set temperature min and max
                        if (isset($temperatureMin[$i])) {
                            $weatherData->setTemperatureMin($temperatureMin[$i]);
                        }
                        if (isset($temperatureMax[$i])) {
                            $weatherData->setTemperatureMax($temperatureMax[$i]);
                        }

                        // Set precipitation
                        if (isset($precipitation[$i])) {
                            $weatherData->setPrecipitation($precipitation[$i]);
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
