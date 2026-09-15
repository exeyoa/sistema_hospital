<?php
// Selector estructurado de cédula panameña (componente reutilizable).
// Uso: define $sel_cedula_id, $sel_cedula_nombre y $sel_cedula_valor,
// y luego:  include __DIR__ . '/componentes/selector_cedula.php';
// Muestra un <select> de prefijo (00/N/E/EC/PE/AV/PI) + 3 campos numéricos
// y un input hidden (id/name = $sel_cedula_id/$sel_cedula_nombre) que
// siempre contiene la cédula combinada en el mismo formato de texto que ya
// espera el backend (ej. "8-123-456" o "PE-1-2345-6789"). No depende de
// clases CSS de ningún proyecto: trae su propio estilo mínimo.
$sel_cedula_id = $sel_cedula_id ?? 'cedula';
$sel_cedula_nombre = $sel_cedula_nombre ?? 'cedula';
$sel_cedula_valor = $sel_cedula_valor ?? '';

if (!isset($GLOBALS['_selector_cedula_css'])) {
    $GLOBALS['_selector_cedula_css'] = true;
    ?>
    <style>
    .sel-cedula {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }
    .sel-cedula select,
    .sel-cedula input {
        padding: 10px 12px;
        border: 1px solid #DDE6E3;
        border-radius: 10px;
        font-size: 15px;
        font-family: inherit;
        background: #fff;
        color: #16241F;
    }
    .sel-cedula select:focus,
    .sel-cedula input:focus {
        outline: none;
        border-color: #0B5D52;
        box-shadow: 0 0 0 3px #E4F2EF;
    }
    .sel-cedula select {
        width: 76px;
    }
    .sel-cedula input.sel-cedula__num {
        width: 84px;
        text-align: center;
    }
    .sel-cedula__sep {
        color: #5C6D68;
        font-weight: 600;
    }
    </style>
    <?php
}
?>
<div class="sel-cedula" role="group" aria-label="Cédula">
    <select class="sel-cedula__prefijo" aria-label="Prefijo de cédula">
        <option value="00">00</option>
        <option value="N">N</option>
        <option value="E">E</option>
        <option value="EC">EC</option>
        <option value="PE">PE</option>
        <option value="AV">AV</option>
        <option value="PI">PI</option>
    </select>
    <input type="text" inputmode="numeric" maxlength="4" class="sel-cedula__num" aria-label="Primer número">
    <span class="sel-cedula__sep">-</span>
    <input type="text" inputmode="numeric" maxlength="4" class="sel-cedula__num" aria-label="Segundo número">
    <span class="sel-cedula__sep">-</span>
    <input type="text" inputmode="numeric" maxlength="4" class="sel-cedula__num" aria-label="Tercer número">
    <input type="hidden" id="<?= htmlspecialchars($sel_cedula_id, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($sel_cedula_nombre, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($sel_cedula_valor, ENT_QUOTES, 'UTF-8') ?>">
</div>
<script>
(function () {
    var iniciado = window.selectorCedulaIniciado;

    function recombinar(div) {
        var prefijo = div.querySelector('.sel-cedula__prefijo');
        var nums = div.querySelectorAll('.sel-cedula__num');
        var partes = [];
        if (prefijo.value !== '00') partes.push(prefijo.value);
        Array.prototype.forEach.call(nums, function (num) {
            var v = num.value.trim();
            if (v !== '') partes.push(v);
        });
        div.querySelector('input[type="hidden"]').value = partes.join('-');
    }

    function inicializar(div) {
        var regexPrefijo = /^[A-Z]{1,2}$/;
        var prefijo = div.querySelector('.sel-cedula__prefijo');
        var nums = div.querySelectorAll('.sel-cedula__num');
        var hidden = div.querySelector('input[type="hidden"]');
        var partes = hidden.value === '' ? [] : hidden.value.split('-');

        if (partes.length > 0 && regexPrefijo.test(partes[0])) {
            var candidato = partes.shift();
            // Solo se usa si el prefijo existe entre las opciones del <select>
            if (prefijo.querySelector('option[value="' + candidato + '"]')) {
                prefijo.value = candidato;
            } else {
                prefijo.value = '00';
            }
        } else {
            prefijo.value = '00';
        }

        Array.prototype.forEach.call(partes, function (p, i) {
            if (nums[i]) nums[i].value = p;
        });
    }

    if (!iniciado) {
        window.selectorCedulaIniciado = true;
        document.addEventListener('input', function (e) {
            var div = e.target.closest('.sel-cedula');
            if (div) recombinar(div);
        });
        document.addEventListener('change', function (e) {
            var div = e.target.closest('.sel-cedula');
            if (div) recombinar(div);
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.sel-cedula'), function (div) {
        if (!div.getAttribute('data-sel-inicializado')) {
            div.setAttribute('data-sel-inicializado', '1');
            inicializar(div);
        }
    });
})();
</script>