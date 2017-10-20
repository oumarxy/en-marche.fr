<?php

namespace AppBundle\Doctrine\Hydrators;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\Committee;
use AppBundle\Entity\Event;
use AppBundle\Entity\EventCategory;
use AppBundle\Entity\ManagedArea;
use AppBundle\Entity\PostAddress;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Ramsey\Uuid\Uuid;

class EventHydrator extends AbstractHydrator
{
    protected function hydrateAllData()
    {
        $result = array();
        foreach ($this->_stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $this->hydrateRowData($row, $result);
        }

        return $result;
    }

    protected function hydrateRowData(array $row, array &$result)
    {
        if (!count($row)) {
            return false;
        }

        if ('FR' === $row['event_address_country']) {
            $addressEvent = $this->createFrenchAddress($row['event_address_address'], $row['event_address_city_insee'], $row['event_address_latitude'], $row['event_address_longitude']);
        } else {
            $addressEvent = $this->createForeignAddress($row['event_address_country'], $row['event_address_postal_code'], $row['event_address_city_name'], $row['event_address_address'], $row['event_address_latitude'], $row['event_address_longitude']);
        }

        if ('FR' === $row['committee_address_country']) {
            $addressCommittee = $this->createFrenchAddress($row['committee_address_address'], $row['committee_address_city_insee'], $row['committee_address_latitude'], $row['committee_address_longitude']);
        } elseif ($row['committee_address_country']) {
            $addressCommittee = $this->createForeignAddress($row['committee_address_country'], $row['committee_address_postal_code'], $row['committee_address_city_name'], $row['committee_address_address'], $row['committee_address_latitude'], $row['committee_address_longitude']);
        }

        if ('FR' === $row['adherent_address_country']) {
            $addressAdherent = $this->createFrenchAddress($row['adherent_address_address'], $row['adherent_address_city_insee'], $row['adherent_address_latitude'], $row['adherent_address_longitude']);
        } else {
            $addressAdherent = $this->createForeignAddress($row['adherent_address_country'], $row['adherent_address_postal_code'], $row['adherent_address_city_name'], $row['adherent_address_address'], $row['adherent_address_latitude'], $row['adherent_address_longitude']);
        }

        $uuidEvent = Uuid::fromString($row['event_uuid']);
        $uuidOrganizer = Uuid::fromString($row['adherent_uuid']);
        $committee = null;
        if ($row['committee_uuid']) {
            $uuidCommittee = Uuid::fromString($row['committee_uuid']);
            $uuidCommitteeOrganizer = $row['committee_created_by'] ? Uuid::fromString($row['committee_created_by']) : $uuidEvent; // to fix the problem on staging where committee can be without creator; this value is used only for 'new Committee()'
            $committee = new Committee($uuidCommittee, $uuidCommitteeOrganizer, $row['committee_name'], $row['committee_description'], $addressCommittee);
        }

        $organizer = new Adherent($uuidOrganizer, $row['adherent_email_address'], $row['adherent_gender'], $row['adherent_first_name'], $row['adherent_last_name'], new \DateTime($row['adherent_birthdate']), $row['adherent_position'], $addressAdherent);
        if ($row['adherent_managed_area_codes']) {
            $managedArea = new ManagedArea();
            $managedArea->setCodes(explode(',', $row['adherent_managed_area_codes']));
            $managedArea->setMarkerLatitude($row['adherent_managed_area_marker_latitude']);
            $managedArea->setMarkerLongitude($row['adherent_managed_area_marker_longitude']);
            $organizer->setManagedArea($managedArea);
        }

        $event = new Event(
            $uuidEvent,
            $organizer,
            $committee,
            $row['event_name'],
            new EventCategory(),
            $row['event_description'],
            $addressEvent,
            $row['event_begin_at'],
            $row['event_finish_at'],
            $row['event_capacity'],
            $row['event_is_for_legislatives'],
            $row['event_created_at'],
            $row['event_participants_count'],
            $row['event_slug'],
            $row['event_type']
        );

        $result[] = $event;
    }

    private function createFrenchAddress(?string $street, ?string $cityCode, ?float $latitude, ?float $longitude): PostAddress
    {
        return PostAddress::createFrenchAddress($street ?? '', $cityCode ?? '-', $latitude, $longitude);
    }

    private function createForeignAddress(?string $country, ?string $zipCode, ?string $cityName, ?string $street, ?float $latitude, ?float $longitude): PostAddress
    {
        return PostAddress::createForeignAddress($country ?? '', $zipCode ?? '', $cityName, $street ?? '', $latitude, $longitude);
    }
}
