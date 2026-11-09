<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Geojson_area
{
    private $points = 0;

    public function validate($data)
    {
        if (!is_array($data) || ($data['type'] ?? NULL) !== 'FeatureCollection' ||
            !isset($data['features']) || !is_array($data['features']) ||
            count($data['features']) < 1 || count($data['features']) > 300)
        {
            throw new InvalidArgumentException('GeoJSON harus FeatureCollection berisi 1 sampai 300 wilayah.');
        }
        $this->points = 0;
        $result = array();
        $seen = array();
        foreach ($data['features'] as $feature)
        {
            if (!is_array($feature) || ($feature['type'] ?? NULL) !== 'Feature' ||
                !isset($feature['properties']) || !is_array($feature['properties']) ||
                !isset($feature['geometry']) || !is_array($feature['geometry']))
            {
                throw new InvalidArgumentException('Setiap wilayah harus berupa Feature dengan properties dan geometry.');
            }
            $props = $feature['properties'];
            $code = (string) ($props['kode_wilayah'] ?? $props['kode'] ?? $props['code'] ?? '');
            $code = trim($code);
            if (!preg_match('/^33\.17\.\d{2}(?:\.\d{4})?$/', $code))
            {
                throw new InvalidArgumentException('Kode wilayah Rembang harus seperti 33.17.01 atau 33.17.01.2001.');
            }
            if (isset($seen[$code])) throw new InvalidArgumentException('Kode wilayah berulang: '.$code);
            $seen[$code] = TRUE;
            $geometry = $feature['geometry'];
            $type = $geometry['type'] ?? '';
            if (!in_array($type, array('Polygon', 'MultiPolygon'), TRUE) ||
                !isset($geometry['coordinates']) || !is_array($geometry['coordinates']))
            {
                throw new InvalidArgumentException('Geometri harus Polygon atau MultiPolygon.');
            }
            $polygons = $type === 'Polygon' ? array($geometry['coordinates']) : $geometry['coordinates'];
            if (!$polygons || count($polygons) > 100)
            {
                throw new InvalidArgumentException('Jumlah polygon tidak valid.');
            }
            foreach ($polygons as $polygon)
            {
                if (!is_array($polygon) || !$polygon || count($polygon) > 100)
                    throw new InvalidArgumentException('Ring polygon tidak valid.');
                foreach ($polygon as $ring)
                {
                    if (!is_array($ring) || count($ring) < 4 || count($ring) > 50000)
                        throw new InvalidArgumentException('Polygon perlu ring tertutup dengan minimal 4 titik.');
                    foreach ($ring as $point)
                    {
                        $this->points++;
                        if ($this->points > 100000 || !is_array($point) || count($point) < 2 ||
                            !is_numeric($point[0]) || !is_numeric($point[1]) ||
                            $point[0] < 110.8 || $point[0] > 111.8 ||
                            $point[1] < -7.3 || $point[1] > -6.3)
                        {
                            throw new InvalidArgumentException('Koordinat berada di luar rentang Rembang atau terlalu banyak titik.');
                        }
                    }
                    if ($ring[0][0] != $ring[count($ring)-1][0] || $ring[0][1] != $ring[count($ring)-1][1])
                        throw new InvalidArgumentException('Ring polygon belum tertutup.');
                }
            }
            $result[] = array('code' => $code, 'type' => substr_count($code, '.') === 2 ? 'district' : 'village',
                              'geometry' => $geometry);
        }
        return $result;
    }
}
