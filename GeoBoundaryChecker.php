<?php
class GeoBoundaryChecker {

    private $cityPolygon;
    private $neighbourhoods = [];
    private $approved = [];

    public function __construct($cityDocId, $neighDocId, $approvedDocId) {
        // Load city boundary
        $cityFile = \REDCap::getFile($cityDocId);
        $cityGeojson = json_decode($cityFile['content'], true);
        $this->cityPolygon = $this->flattenPolygon($cityGeojson['features'][0]['geometry']['coordinates'][0]);

        // Load neighbourhood boundaries
        $neighFile = \REDCap::getFile($neighDocId);
        $neighGeojson = json_decode($neighFile['content'], true);

        // Load approved neighbourhood list
        $approvedFile = \REDCap::getFile($approvedDocId);
        $this->approved = array_flip(json_decode($approvedFile['content'], true)); // flip for fast lookup

        // Flatten polygons and compute bounding boxes
        foreach ($neighGeojson['features'] as $feature) {
            $name = $feature['properties']['NEIGHBOURHOOD'];
            if (!isset($this->approved[$name])) continue; // skip unapproved

            $polygon = $this->flattenPolygon($feature['geometry']['coordinates'][0]);
            $bbox = $this->computeBoundingBox($polygon);

            $this->neighbourhoods[] = [
                'name' => $name,
                'polygon' => $polygon,
                'bbox' => $bbox
            ];
        }
    }

    // PUBLIC: check a point, returns city + neighbourhood
    public function checkPoint($lon, $lat) {
        $point = [$lon, $lat];

        // Step 1: check city boundary
        if (!$this->pointInPolygon($point, $this->cityPolygon)) {
            return ['city' => 0, 'neighbourhood' => null];
        }

        // Step 2: loop neighbourhoods
        foreach ($this->neighbourhoods as $n) {
            list($minX,$maxX,$minY,$maxY) = $n['bbox'];
            if ($lon < $minX || $lon > $maxX || $lat < $minY || $lat > $maxY) {
                continue; // outside bounding box, skip
            }

            if ($this->pointInPolygon($point, $n['polygon'])) {
                return ['city' => 1, 'neighbourhood' => $n['name']];
            }
        }

        return ['city' => 1, 'neighbourhood' => null]; // inside city but not in any approved neighbourhood
    }

    // Flatten polygon: returns simple [ [x,y], ... ]
    private function flattenPolygon($coords) {
        $flat = [];
        foreach ($coords as $c) {
            $flat[] = [$c[0], $c[1]];
        }
        return $flat;
    }

    // Compute bounding box: [minX, maxX, minY, maxY]
    private function computeBoundingBox($polygon) {
        $xs = array_column($polygon, 0);
        $ys = array_column($polygon, 1);
        return [min($xs), max($xs), min($ys), max($ys)];
    }

    // Standard ray-casting point-in-polygon
    private function pointInPolygon($point, $polygon) {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $count = count($polygon);
        for ($i=0, $j=$count-1; $i<$count; $j=$i++) {
            $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
            $xj = $polygon[$j][0]; $yj = $polygon[$j][1];

            $intersect = (($yi > $y) != ($yj > $y)) &&
                         ($x < ($xj-$xi)*($y-$yi)/($yj-$yi)+$xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

}
