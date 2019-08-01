<?php

namespace DHLParcel\Shipping\Model\Service\Logic;

use setasign\Fpdi\Tcpdf\Fpdi;
use setasign\Fpdi\Tcpdf\FpdiFactory;
use setasign\Fpdi\PdfParser\StreamReader;

class PDFMerger
{
    protected $fpdiFactory;

    /**
     * PDFMerger constructor.
     */
    public function __construct(
        FpdiFactory $fpdiFactory
    ) {
        $this->fpdiFactory = $fpdiFactory;
    }

    /**
     * @param $PDFs
     * @param int $width
     * @param int $height
     * @return mixed
     * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
     * @throws \setasign\Fpdi\PdfParser\Filter\FilterException
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     * @throws \setasign\Fpdi\PdfParser\Type\PdfTypeException
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     */
    public function mergePDFs($PDFs, $width = 1, $height = 1)
    {
        /** @var Fpdi $fpdi */
        $fpdi = $this->fpdiFactory->create();
        $counter = 0;
        $stack = $width * $height;
        $orientation = $width > $height ? 'L' : 'P';
        foreach ($PDFs as $PDF) {
            $stream = StreamReader::createByString($PDF);
            $count = $fpdi->setSourceFile($stream);
            for ($i = 1; $i <= $count; $i++) {
                $template = $fpdi->importPage($i);
                $size = $fpdi->getTemplateSize($template);
                if (($counter % $stack) === 0) {
                    if ($orientation == 'L') {
                        $fpdi->AddPage('L', [$size['width'] * $width, $size['height'] * $height]);
                    } else {
                        $fpdi->AddPage('P', [$size['width'] * $width, $size['height'] * $height]);
                    }
                }
                $fpdi->useTemplate(
                    $template,
                    $size['width'] * ($counter % $width),
                    $size['height'] * (floor($counter / $width) % $height),
                    $size['width'],
                    $size['height'],
                    false
                );

                $counter++;
            }
        }
        return $fpdi->Output('label.pdf', 'S');
    }
}
