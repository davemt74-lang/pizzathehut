<?php
/** Known formulas and provisional/confirmed production steps. Kept separate from business data. */
declare(strict_types=1);
function pizza_lab_recipe_templates(): array {
    return [
      'Gelato v1.0'=>[
        'name'=>'Gelato v1.0','status'=>'current','flour_name'=>'Caputo (specific product not confirmed)',
        'flour_g'=>10000,'water_g'=>5800,'salt_g'=>220,'oil_g'=>null,'honey_g'=>0,'yeast_g'=>30,'yeast_type'=>'fresh compressed',
        'notes'=>'Current production recipe as provided by chef. Oil brushed over the dough before resting: quantity measured per batch, not yet known. Water total: 4,800 g initial + 500 g yeast + 500 g salt. Do not infer a fixed oil weight.',
        'process_notes'=>'Mix flour + 4,800 g ice water 3 min; add 30 g fresh yeast stirred into 500 g ice water. Mix 6 min; add 220 g salt dissolved in 500 g ice water. Mix another 6–8 min. Coat dough with oil (record actual grams), rest covered 35–50 min; ball, refrigerate and record daily temperatures.',
        'steps'=>[
          ['initial_mix','mix','Mix flour + initial water','10,000 g Caputo flour + 4,800 g ice water; mix 3 minutes.',0,3,3],
          ['yeast_add','mix','Add yeast mixture','Stir 30 g fresh compressed yeast into 500 g ice water, then add. Mix for 6 additional minutes.',3,6,6],
          ['salt_add','mix','Add salt mixture','Dissolve 220 g salt in 500 g ice water; add after the preceding six minutes. Mix 6–8 more minutes.',9,6,8],
          ['oil_cover','rest','Apply oil and cover','At start of rest, cover dough with oil; record actual oil grams applied. Amount not specified by recipe.',null,null,null],
          ['bench_rest','rest','Covered room-temperature rest','Rest for 35–50 minutes. Record actual start/end and finished dough temperature.',null,35,50],
          ['ball','ball','Divide and ball','After resting, shape individual dough balls; record date, portion counts, and weights.',null,null,null],
          ['refrigerate','cold_ball','Refrigerate balled dough','Refrigerate promptly. Log each dough mass/container temperature daily; record dates pizzas are used.',null,null,null],
        ]],
      'Stonefellows v1.0'=>[
        'name'=>'Stonefellows v1.0','status'=>'experimental','flour_name'=>'Caputo Pizzeria Blue 12.5%',
        'flour_g'=>11793.4,'water_g'=>7144.1,'salt_g'=>284,'oil_g'=>220,'honey_g'=>120,'yeast_g'=>85,'yeast_type'=>'fresh compressed',
        'notes'=>'New experimental pizza dough: 26 lb Caputo Pizzeria Blue, 15.75 lb total ice water, 284 g salt, 85 g fresh compressed yeast, 220 g oil and 120 g honey. User-confirmed mixing sequence: water, salt and yeast first, then flour; 6 min mixing; add oil and honey; mix 8 min more. No additional water outside formula. 24 h cold bulk and 48 h balled are test targets.',
        'process_notes'=>'Combine all 7,144.1 g ice water with 284 g salt and 85 g fresh compressed yeast, then add 11,793.4 g flour. Mix 6 min. At minute 6 add 220 g oil and 120 g honey; mix another 8 min (14 min mixer run total). Measure finished dough temperature; split into two shallow bins and refrigerate promptly for 24 h cold bulk. Ball and refrigerate 48 h more, then record pizza evidence. Do not pre-ferment yeast for an extended period in concentrated salt water.',
        'steps'=>[
          ['combine_water_yeast_salt','mix','Add water, salt and yeast','Add 7,144.1 g TOTAL ice water, 284 g salt and 85 g fresh compressed yeast to the mixer; stir/disperse, then add the flour promptly. This uses all formula water; do not add extra.',0,null,null],
          ['flour_mix_6','mix','Add Caputo flour; mix 6 minutes','Add 11,793.4 g Caputo Pizzeria Blue flour after water, salt and yeast. Run mixer 6 minutes. Record actual start and finish.',0,6,6],
          ['oil_honey_mix_8','mix','Add oil and honey; mix 8 minutes','At 6 minutes add 220 g oil and 120 g honey. Run mixer 8 more minutes (14 minutes mixing total). Record actual mixing time and finished dough temperature.',6,8,8],
          ['split_cool','cold_bulk','Split into two bins and refrigerate','After mixing, split into two shallow, covered food-safe proofing bins (about half of total dough per bin). Record each container mass and center temperature; refrigerate promptly.',14,null,null],
          ['bulk_24h','cold_bulk','24-hour cold bulk fermentation','Refrigerate in two shallow bins for a target of 24 hours, counting from refrigerator entry. Track dough center temperature per container and refrigerator temperature.',null,1440,1440],
          ['ball','ball','Divide and ball dough','After 24 hours of cold bulk, divide into individual dough balls. Record ball date, count and portion weights (e.g. 220 g for 12-inch, 160 g for 10-inch).',null,null,null],
          ['ball_48h','cold_ball','48-hour refrigerated ball fermentation','Refrigerate the balls for a target of 48 more hours, monitoring temperatures, expansion and condition. Record actual start and end; 72 hours overall is experimental, not guaranteed optimal.',null,2880,2880],
          ['bake','bake','Bake, photograph and score pizza','Record batch code, measured stone temperature, actual bake time, sauce/cheese weights, rise, crispness, color, photos and waste if applicable.',null,null,null],
        ]],
    ];
}
function pizza_lab_seed_recipes(PDO $db): array {
    $messages=[];
    $templates=pizza_lab_recipe_templates();
    foreach($templates as $name=>$data) {
        $get=$db->prepare('SELECT * FROM recipes WHERE name=? ORDER BY id ASC LIMIT 1');
        $get->execute([$name]);$row=$get->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $legacy=$name==='Gelato v1.0'?'Current Work Recipe — Enter Ingredients':'85g Fresh Yeast · 24h + 48h';
            $get->execute([$legacy]);$candidate=$get->fetch(PDO::FETCH_ASSOC);
            $ok=$candidate && ($name==='Gelato v1.0'
              ? $candidate['flour_g']===null && $candidate['water_g']===null && $candidate['salt_g']===null && (float)$candidate['yeast_g']===30.0
              : (float)$candidate['yeast_g']===85.0 && (float)$candidate['honey_g']===120.0 && (float)$candidate['flour_g']>11790);
            if ($ok) {
                $db->prepare('UPDATE recipes SET name=?,status=?,flour_name=?,flour_g=?,water_g=?,salt_g=?,oil_g=?,honey_g=?,yeast_g=?,yeast_type=?,process_notes=?,notes=? WHERE id=?')->execute([
                    $name,$data['status'],$data['flour_name'],$data['flour_g'],$data['water_g'],$data['salt_g'],$data['oil_g'],$data['honey_g'],$data['yeast_g'],$data['yeast_type'],$data['process_notes'],$data['notes'],$candidate['id']
                ]);
                $id=(int)$candidate['id'];$messages[]='Updated unchanged legacy formula to '.$name;
            } else {
                $db->prepare('INSERT INTO recipes (name,status,flour_name,flour_g,water_g,salt_g,oil_g,honey_g,yeast_g,yeast_type,process_notes,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                    $name,$data['status'],$data['flour_name'],$data['flour_g'],$data['water_g'],$data['salt_g'],$data['oil_g'],$data['honey_g'],$data['yeast_g'],$data['yeast_type'],$data['process_notes'],$data['notes']
                ]);
                $id=(int)$db->lastInsertId();$messages[]='Created '.$name;
            }
        } else $id=(int)$row['id'];
        $count=$db->prepare('SELECT COUNT(*) FROM recipe_steps WHERE recipe_id=?');$count->execute([$id]);
        if ((int)$count->fetchColumn()===0) {
            $ins=$db->prepare('INSERT INTO recipe_steps (recipe_id,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach($data['steps'] as $i=>$s) $ins->execute([$id,$s[0],($i+1)*10,$s[1],$s[2],$s[3],$s[4],$s[5],$s[6]]);
            $messages[]='Added process timeline for '.$name;
        }
    }
    return $messages;
}

/** Upgrade only the exact, untouched provisional Stonefellows template shipped in v1.3.0.
 * Never replace user-customized recipe steps, and never alter frozen batch snapshots.
 */
function pizza_lab_upgrade_default_stonefellows_process(PDO $db): array {
    $row=$db->prepare('SELECT * FROM recipes WHERE name=? ORDER BY id LIMIT 1');
    $row->execute(['Stonefellows v1.0']); $recipe=$row->fetch(PDO::FETCH_ASSOC);
    if (!$recipe) return [];
    $fetch=$db->prepare('SELECT step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order,id');
    $fetch->execute([(int)$recipe['id']]); $steps=$fetch->fetchAll(PDO::FETCH_ASSOC);
    $fingerprint=[];
    foreach($steps as $s) $fingerprint[]=[
        (string)$s['step_key'], (string)(int)$s['sort_order'], (string)$s['stage'],
        (string)$s['title'], (string)$s['instructions'],
        ...array_map(fn($v)=>$v===null?null:(string)(float)$v,[$s['target_start_min'],$s['target_min_minutes'],$s['target_max_minutes']])
    ];
    if (hash('sha256',json_encode($fingerprint,JSON_UNESCAPED_UNICODE)) !== '08056e663219f55554d9d9e96e1ce8cbfef56f860aecacd89894f2eb6a2079fb') return [];
    $new=pizza_lab_recipe_templates()['Stonefellows v1.0'];
    $db->beginTransaction();
    try {
        $del=$db->prepare('DELETE FROM recipe_steps WHERE recipe_id=?');$del->execute([$recipe['id']]);
        $ins=$db->prepare('INSERT INTO recipe_steps (recipe_id,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach($new['steps'] as $i=>$s) $ins->execute([(int)$recipe['id'],$s[0],($i+1)*10,$s[1],$s[2],$s[3],$s[4],$s[5],$s[6]]);
        // Update notes only when their contents are still the original default.
        $db->prepare('UPDATE recipes SET notes=? WHERE id=? AND notes=?')->execute([$new['notes'],$recipe['id'],'NEW proposed formula, not the currently used Gelato recipe. Flour and water are 26 lb and 15.75 lb converted to grams. Total recipe oil 220 g and honey 120 g. Final mixing schedule and timing for oil addition are provisional; confirm during first test. 24 h cold bulk + 48 h balled; 2 shallow bulk bins.']);
        $db->prepare('UPDATE recipes SET process_notes=? WHERE id=? AND process_notes=?')->execute([$new['process_notes'],$recipe['id'],'Proposed 24-hour cold bulk fermentation (split into 2 shallow bins), followed by balling and 48-hour refrigerated ball proof. Refrigerate immediately after mixing; log center temperatures, actual stage times, baked pizza outcomes and waste. Mixing timings below are suggested starting points only, not an observed work process.']);
        $db->commit();
    } catch(Throwable $e) {$db->rollBack();throw $e;}
    return ['Updated untouched Stonefellows mixing template: water + salt + yeast, flour 6 min, oil + honey 8 min'];
}
