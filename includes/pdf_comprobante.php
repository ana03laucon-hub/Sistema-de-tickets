<?php
/**
 * Generador mínimo de PDF para comprobantes de ingreso.
 * No requiere Composer ni librerías externas.
 */

class ComprobanteIngresoPDF
{
    private array $objects = [];
    private array $pages = [];
    private float $width = 612;
    private float $height = 792;
    private float $margin = 42;
    private array $current = [];
    private float $y = 750;

    public function __construct()
    {
        $this->current = [];
    }

    private function textoPDF(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
        if ($convertido === false) {
            $convertido = utf8_decode($texto);
        }
        return $convertido;
    }

    private function escapar(string $texto): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->textoPDF($texto));
    }

    private function agregarOperacion(string $operacion): void
    {
        $this->current[] = $operacion;
    }

    private function texto(float $x, float $y, string $texto, float $tam = 10, bool $negrita = false): void
    {
        $fuente = $negrita ? '/F2' : '/F1';
        $this->agregarOperacion(sprintf(
            'BT %s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $fuente,
            $tam,
            $x,
            $y,
            $this->escapar($texto)
        ));
    }

    private function linea(float $x1, float $y1, float $x2, float $y2, float $grosor = 0.8): void
    {
        $this->agregarOperacion(sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $grosor, $x1, $y1, $x2, $y2));
    }

    private function rectangulo(float $x, float $y, float $w, float $h, bool $relleno = false): void
    {
        $this->agregarOperacion(sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $w, $h, $relleno ? 'f' : 'S'));
    }

    private function iniciarPagina(): void
    {
        if (!empty($this->current)) {
            $this->pages[] = implode("\n", $this->current);
        }
        $this->current = [];
        $this->y = 750;
    }

    private function asegurarEspacio(float $alto): void
    {
        if ($this->y - $alto < 45) {
            $this->iniciarPagina();
        }
    }

    private function textoEnvuelto(string $texto, float $x, float $y, float $maxAncho, float $tam = 10, bool $negrita = false, float $interlineado = 14): float
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if ($texto === '') {
            return $y;
        }

        $caracteres = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY);
        $linea = '';
        $lineas = [];

        foreach ($caracteres as $caracter) {
            $prueba = $linea . $caracter;
            $anchoAprox = strlen($this->textoPDF($prueba)) * ($tam * 0.48);
            if ($anchoAprox > $maxAncho && $linea !== '') {
                $lineas[] = $linea;
                $linea = $caracter;
            } else {
                $linea = $prueba;
            }
        }
        if ($linea !== '') {
            $lineas[] = $linea;
        }

        foreach ($lineas as $textoLinea) {
            $this->asegurarEspacio($interlineado + 8);
            $this->texto($x, $y, $textoLinea, $tam, $negrita);
            $y -= $interlineado;
        }
        return $y;
    }

    public function generar(array $datos): string
    {
        $this->iniciarPagina();

        // Encabezado institucional.
        $this->texto(42, 750, 'GOBIERNO MUNICIPAL 2024-2027', 12, true);
        $this->texto(42, 733, 'H. AYUNTAMIENTO DE TEHUACAN', 10, true);
        $this->texto(350, 750, 'COORDINACION DE', 11, true);
        $this->texto(350, 735, 'GOBIERNO ELECTRONICO', 11, true);
        $this->linea(42, 718, 570, 718, 1.2);

        $this->texto(115, 688, 'COMPROBANTE DE INGRESO DE EQUIPO', 15, true);
        $this->texto(214, 670, 'PARA SOPORTE TECNICO', 11, true);

        $this->rectangulo(42, 620, 528, 34);
        $this->texto(55, 640, 'Folio:', 10, true);
        $this->texto(92, 640, (string)$datos['folio'], 11, true);
        $this->texto(350, 640, 'Fecha de ingreso:', 10, true);
        $this->texto(445, 640, (string)$datos['fecha_ingreso'], 10, false);

        $y = 595;
        $this->texto(42, $y, 'DATOS DE ENTREGA', 11, true);
        $y -= 22;

        $campos = [
            ['Usuario que entrega', $datos['usuario']],
            ['Area / Departamento', $datos['departamento']],
            ['Equipo', $datos['equipo']],
            ['Cables / accesorios', $datos['cables']],
            ['Ticket relacionado', $datos['ticket']],
        ];

        foreach ($campos as $campo) {
            $this->asegurarEspacio(25);
            $this->texto(55, $y, $campo[0] . ':', 10, true);
            $this->textoEnvuelto($campo[1], 180, $y, 370, 10, false, 13);
            $y -= 27;
        }

        $y -= 4;
        $this->texto(42, $y, 'DESCRIPCION / ESTADO FISICO', 11, true);
        $y -= 18;
        $this->rectangulo(42, $y - 88, 528, 88);
        $y -= 17;
        $y = $this->textoEnvuelto($datos['descripcion'], 55, $y, 500, 10, false, 14);
        // Deja espacio para que la sección de recepción quede fuera del recuadro.
        $y -= 55;

        $this->asegurarEspacio(130);
        $this->texto(42, $y, 'RECEPCION', 11, true);
        $y -= 22;
        $this->texto(55, $y, 'Recibe / recepciona:', 10, true);
        $this->textoEnvuelto($datos['recepciona'], 180, $y, 360, 10, false, 13);
        $y -= 28;
        $this->texto(55, $y, 'Fecha y hora:', 10, true);
        $this->texto(180, $y, $datos['fecha_ingreso'], 10);
        $y -= 55;

        $this->linea(75, $y, 255, $y, 0.8);
        $this->linea(357, $y, 537, $y, 0.8);
        $this->texto(98, $y - 18, 'ENTREGA', 9, true);
        $this->texto(401, $y - 18, 'RECEPCIONA', 9, true);
        $this->textoEnvuelto($datos['usuario'], 70, $y - 34, 190, 8, false, 10);
        $this->textoEnvuelto($datos['recepciona'], 352, $y - 34, 190, 8, false, 10);

        $this->texto(42, 45, 'Este comprobante acredita el ingreso fisico del equipo al area de soporte tecnico.', 8, false);
        $this->texto(470, 45, 'Folio ' . $datos['folio'], 8, true);

        if (!empty($this->current)) {
            $this->pages[] = implode("\n", $this->current);
        }

        return $this->construirPDF();
    }

    private function construirPDF(): string
    {
        $objetos = [];
        $objetos[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $cantidadPaginas = count($this->pages);
        $kids = [];
        for ($i = 0; $i < $cantidadPaginas; $i++) {
            $kids[] = (3 + ($i * 2)) . ' 0 R';
        }
        $objetos[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $cantidadPaginas . ' >>';

        $fontRegular = 3 + (2 * $cantidadPaginas);
        $fontBold = $fontRegular + 1;

        foreach ($this->pages as $contenido) {
            $indicePagina = count($objetos) + 1;
            $indiceContenido = $indicePagina + 1;
            $objetos[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents ' . $indiceContenido . ' 0 R >>';
            $longitud = strlen($contenido);
            $objetos[] = "<< /Length $longitud >>\nstream\n$contenido\nendstream";
        }

        $objetos[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objetos as $i => $objeto) {
            $numero = $i + 1;
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $objeto . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}
