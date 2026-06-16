<?php
use App\Models\Challenge;

$i = 0;
foreach (Challenge::orderByDesc('id')->get() as $c) {
    $c->update(['sort_order' => $i]);
    echo "Challenge {$c->id} ({$c->title}) => sort_order {$i}\n";
    $i++;
}
