<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
header("Location: producto_form.php", true, 302);
exit;
