<?php
// app/helpers.php

if (!function_exists('formatMoney')) {
    /**
     * Formata um valor numérico como uma string de moeda em Reais (BRL).
     * @param float $value O valor a ser formatado.
     * @return string A string formatada (ex: R$ 10,00).
     */
    function formatMoney($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
