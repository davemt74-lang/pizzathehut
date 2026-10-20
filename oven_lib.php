<?php
/** Pure domain validation. Every physical reading is supplied by a user, never interpolated as fact. */
declare(strict_types=1);
function lab_ellipse_inside(float $x, float $y): bool {
    return $x>=0 && $x<=1 && $y>=0 && $y<=1 && (4*($x-.5)**2+4*($y-.5)**2)<=1.00001;
}
function lab_oven_diameter(float $ns,float $ew): array {
    if(!is_finite($ns)||!is_finite($ew)||$ns<12||$ew<12||$ns>600||$ew>600) throw new InvalidArgumentException('Oven dimensions must be 12–600 inches.');
    return ['north_south_in'=>$ns,'east_west_in'=>$ew,'largest_centered_circle_in'=>min($ns,$ew),'approx_ellipse_sq_in'=>round(M_PI*$ns*$ew/4,1)];
}
function lab_oven_zones(array $zones): array {
    if(count($zones)>12) throw new InvalidArgumentException('Maximum 12 named heat zones.');
    $out=[];$ids=[];
    foreach($zones as $i=>$z){
        if(!is_array($z)) throw new InvalidArgumentException('Invalid zone.');
        $key=(string)($z['key']??'');$name=trim((string)($z['name']??''));
        if(!preg_match('/^[a-z0-9_-]{2,28}$/',$key)||isset($ids[$key])||$name===''||strlen($name)>80) throw new InvalidArgumentException('Zone needs a distinct key and name.');
        $x=(float)($z['x']??-1);$y=(float)($z['y']??-1);
        if(!lab_ellipse_inside($x,$y)) throw new InvalidArgumentException('Zone must be inside the floor outline.');
        $radius=(float)($z['radius_in']??0);
        if(!is_finite($radius)||$radius<1||$radius>200)throw new InvalidArgumentException('Zone radius must be 1–200 inches.');
        $ids[$key]=true;$out[]=['key'=>$key,'name'=>$name,'x'=>round($x,4),'y'=>round($y,4),'radius_in'=>round($radius,2)];
    }
    return $out;
}
