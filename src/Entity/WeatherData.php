<?php

namespace App\Entity;

use App\Repository\WeatherDataRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=WeatherDataRepository::class)
 */
class WeatherData
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $sessionId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $city;

    /**
     * @ORM\Column(type="float")
     */
    private $latitude;

    /**
     * @ORM\Column(type="float")
     */
    private $longitude;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $temperature_min;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $temperature_max;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $temperature_mean;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $precipitation;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $wind_speed_max;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $wind_gusts_max;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_activity;

    /**
     * @return mixed
     */
    public function getTemperatureMean()
    {
        return $this->temperature_mean;
    }

    /**
     * @param mixed $temperature_mean
     */
    public function setTemperatureMean($temperature_mean): void
    {
        $this->temperature_mean = $temperature_mean;
    }

    /**
     * @return mixed
     */
    public function getWindSpeedMax()
    {
        return $this->wind_speed_max;
    }

    /**
     * @param mixed $wind_speed_max
     */
    public function setWindSpeedMax($wind_speed_max): void
    {
        $this->wind_speed_max = $wind_speed_max;
    }

    /**
     * @return mixed
     */
    public function getWindGustsMax()
    {
        return $this->wind_gusts_max;
    }

    /**
     * @param mixed $wind_gusts_max
     */
    public function setWindGustsMax($wind_gusts_max): void
    {
        $this->wind_gusts_max = $wind_gusts_max;
    }


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param mixed $sessionId
     */
    public function setSessionId($sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @return mixed
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param mixed $city
     */
    public function setCity($city): void
    {
        $this->city = $city;
    }

    /**
     * @return mixed
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param mixed $latitude
     */
    public function setLatitude($latitude): void
    {
        $this->latitude = $latitude;
    }

    /**
     * @return mixed
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param mixed $longitude
     */
    public function setLongitude($longitude): void
    {
        $this->longitude = $longitude;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date): void
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getTemperatureMin()
    {
        return $this->temperature_min;
    }

    /**
     * @param mixed $temperature_min
     */
    public function setTemperatureMin($temperature_min): void
    {
        $this->temperature_min = $temperature_min;
    }

    /**
     * @return mixed
     */
    public function getTemperatureMax()
    {
        return $this->temperature_max;
    }

    /**
     * @param mixed $temperature_max
     */
    public function setTemperatureMax($temperature_max): void
    {
        $this->temperature_max = $temperature_max;
    }

    /**
     * @return mixed
     */
    public function getPrecipitation()
    {
        return $this->precipitation;
    }

    /**
     * @param mixed $precipitation
     */
    public function setPrecipitation($precipitation): void
    {
        $this->precipitation = $precipitation;
    }

    /**
     * @return mixed
     */
    public function getLastActivity()
    {
        return $this->last_activity;
    }

    /**
     * @param mixed $last_activity
     */
    public function setLastActivity($last_activity): void
    {
        $this->last_activity = $last_activity;
    }


}
