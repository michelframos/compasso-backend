<?php
$r = App\Models\Responsavel::find(1);
if ($r) {
    echo "Relacionamento usuario: ";
    $r->load('usuario');
    if ($r->usuario) {
        echo "Found User ID: " . $r->usuario->id . "\n";
    } else {
        echo "NULL\n";
    }
}
