<?php

namespace arabcoders\getid3\Module\Graphic;

use arabcoders\getid3\Handler\BaseHandler;
use arabcoders\getid3\Lib\Helper;
use arabcoders\getid3\Interfaces\ModuleInterface;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.graphic.png.php                                      //
// module for analyzing PNG Image files                        //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for analyzing PNG Image files
 *
 * @author James Heinrich <info@getid3.org>
 *
 * @link   http://getid3.sourceforge.net
 * @link   http://www.getid3.org
 */
class Png extends BaseHandler implements ModuleInterface
{
    /**
     * @return bool
     */
    public function analyze()
    {
        $info = &$this->getid3->info;

        // shortcut
        $info['png']  = [];
        $thisfile_png = &$info['png'];

        $info['fileformat']          = 'png';
        $info['video']['dataformat'] = 'png';
        $info['video']['lossless']   = false;

        fseek( $this->getid3->fp, $info['avdataoffset'], SEEK_SET );
        $PNGfiledata = fread( $this->getid3->fp, $this->getid3->fread_buffer_size() );
        $offset      = 0;

        $PNGidentifier = substr( $PNGfiledata, $offset, 8 ); // $89 $50 $4E $47 $0D $0A $1A $0A
        $offset += 8;

        if ( $PNGidentifier != "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" )
        {
            $info['error'][] = 'First 8 bytes of file (' . Helper::PrintHexBytes( $PNGidentifier ) . ') did not match expected PNG identifier';
            unset( $info['fileformat'] );

            return false;
        }

        while ( ( ( ftell( $this->getid3->fp ) - ( strlen( $PNGfiledata ) - $offset ) ) < $info['filesize'] ) )
        {
            $chunk['data_length'] = Helper::BigEndian2Int( substr( $PNGfiledata, $offset, 4 ) );
            $offset += 4;
            while ( ( ( strlen( $PNGfiledata ) - $offset ) < ( $chunk['data_length'] + 4 ) ) && ( ftell( $this->getid3->fp ) < $info['filesize'] ) )
            {
                $PNGfiledata .= fread( $this->getid3->fp, $this->getid3->fread_buffer_size() );
            }
            $chunk['type_text'] = substr( $PNGfiledata, $offset, 4 );
            $offset += 4;
            $chunk['type_raw'] = Helper::BigEndian2Int( $chunk['type_text'] );
            $chunk['data']     = substr( $PNGfiledata, $offset, $chunk['data_length'] );
            $offset += $chunk['data_length'];
            $chunk['crc'] = Helper::BigEndian2Int( substr( $PNGfiledata, $offset, 4 ) );
            $offset += 4;

            $chunk['flags']['ancilliary']   = (bool) ( $chunk['type_raw'] & 0x20000000 );
            $chunk['flags']['private']      = (bool) ( $chunk['type_raw'] & 0x00200000 );
            $chunk['flags']['reserved']     = (bool) ( $chunk['type_raw'] & 0x00002000 );
            $chunk['flags']['safe_to_copy'] = (bool) ( $chunk['type_raw'] & 0x00000020 );

            // shortcut
            $thisfile_png[$chunk['type_text']] = [];
            $thisfile_png_chunk_type_text      = &$thisfile_png[$chunk['type_text']];

            switch ( $chunk['type_text'] )
            {

                case 'IHDR': // Image Header
                    $thisfile_png_chunk_type_text['header']                    = $chunk;
                    $thisfile_png_chunk_type_text['width']                     = Helper::BigEndian2Int( substr( $chunk['data'], 0, 4 ) );
                    $thisfile_png_chunk_type_text['height']                    = Helper::BigEndian2Int( substr( $chunk['data'], 4, 4 ) );
                    $thisfile_png_chunk_type_text['raw']['bit_depth']          = Helper::BigEndian2Int( substr( $chunk['data'], 8, 1 ) );
                    $thisfile_png_chunk_type_text['raw']['color_type']         = Helper::BigEndian2Int( substr( $chunk['data'], 9, 1 ) );
                    $thisfile_png_chunk_type_text['raw']['compression_method'] = Helper::BigEndian2Int( substr( $chunk['data'], 10, 1 ) );
                    $thisfile_png_chunk_type_text['raw']['filter_method']      = Helper::BigEndian2Int( substr( $chunk['data'], 11, 1 ) );
                    $thisfile_png_chunk_type_text['raw']['interlace_method']   = Helper::BigEndian2Int( substr( $chunk['data'], 12, 1 ) );

                    $thisfile_png_chunk_type_text['compression_method_text']  = $this->PNGcompressionMethodLookup( $thisfile_png_chunk_type_text['raw']['compression_method'] );
                    $thisfile_png_chunk_type_text['color_type']['palette']    = (bool) ( $thisfile_png_chunk_type_text['raw']['color_type'] & 0x01 );
                    $thisfile_png_chunk_type_text['color_type']['true_color'] = (bool) ( $thisfile_png_chunk_type_text['raw']['color_type'] & 0x02 );
                    $thisfile_png_chunk_type_text['color_type']['alpha']      = (bool) ( $thisfile_png_chunk_type_text['raw']['color_type'] & 0x04 );

                    $info['video']['resolution_x'] = $thisfile_png_chunk_type_text['width'];
                    $info['video']['resolution_y'] = $thisfile_png_chunk_type_text['height'];

                    $info['video']['bits_per_sample'] = $this->IHDRcalculateBitsPerSample( $thisfile_png_chunk_type_text['raw']['color_type'], $thisfile_png_chunk_type_text['raw']['bit_depth'] );
                    break;

                case 'PLTE': // Palette
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    $paletteoffset                          = 0;
                    for ( $i = 0; $i <= 255; ++$i )
                    {
                        //$thisfile_png_chunk_type_text['red'][$i]   = GetId3_lib::BigEndian2Int(substr($chunk['data'], $paletteoffset++, 1));
                        //$thisfile_png_chunk_type_text['green'][$i] = GetId3_lib::BigEndian2Int(substr($chunk['data'], $paletteoffset++, 1));
                        //$thisfile_png_chunk_type_text['blue'][$i]  = GetId3_lib::BigEndian2Int(substr($chunk['data'], $paletteoffset++, 1));
                        $red                              = Helper::BigEndian2Int( substr( $chunk['data'], $paletteoffset++, 1 ) );
                        $green                            = Helper::BigEndian2Int( substr( $chunk['data'], $paletteoffset++, 1 ) );
                        $blue                             = Helper::BigEndian2Int( substr( $chunk['data'], $paletteoffset++, 1 ) );
                        $thisfile_png_chunk_type_text[$i] = ( ( $red << 16 ) | ( $green << 8 ) | ( $blue ) );
                    }
                    break;

                case 'tRNS': // Transparency
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    switch ( $thisfile_png['IHDR']['raw']['color_type'] )
                    {
                        case 0:
                            $thisfile_png_chunk_type_text['transparent_color_gray'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 2 ) );
                            break;

                        case 2:
                            $thisfile_png_chunk_type_text['transparent_color_red']   = Helper::BigEndian2Int( substr( $chunk['data'], 0, 2 ) );
                            $thisfile_png_chunk_type_text['transparent_color_green'] = Helper::BigEndian2Int( substr( $chunk['data'], 2, 2 ) );
                            $thisfile_png_chunk_type_text['transparent_color_blue']  = Helper::BigEndian2Int( substr( $chunk['data'], 4, 2 ) );
                            break;

                        case 3:
                            for ( $i = 0; $i < strlen( $chunk['data'] ); ++$i )
                            {
                                $thisfile_png_chunk_type_text['palette_opacity'][$i] = Helper::BigEndian2Int( substr( $chunk['data'], $i, 1 ) );
                            }
                            break;

                        case 4:
                        case 6:
                            $info['error'][] = 'Invalid color_type in tRNS chunk: ' . $thisfile_png['IHDR']['raw']['color_type'];

                        default:
                            $info['warning'][] = 'Unhandled color_type in tRNS chunk: ' . $thisfile_png['IHDR']['raw']['color_type'];
                            break;
                    }
                    break;

                case 'gAMA': // Image Gamma
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    $thisfile_png_chunk_type_text['gamma']  = Helper::BigEndian2Int( $chunk['data'] ) / 100000;
                    break;

                case 'cHRM': // Primary Chromaticities
                    $thisfile_png_chunk_type_text['header']  = $chunk;
                    $thisfile_png_chunk_type_text['white_x'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['white_y'] = Helper::BigEndian2Int( substr( $chunk['data'], 4, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['red_y']   = Helper::BigEndian2Int( substr( $chunk['data'], 8, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['red_y']   = Helper::BigEndian2Int( substr( $chunk['data'], 12, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['green_y'] = Helper::BigEndian2Int( substr( $chunk['data'], 16, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['green_y'] = Helper::BigEndian2Int( substr( $chunk['data'], 20, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['blue_y']  = Helper::BigEndian2Int( substr( $chunk['data'], 24, 4 ) ) / 100000;
                    $thisfile_png_chunk_type_text['blue_y']  = Helper::BigEndian2Int( substr( $chunk['data'], 28, 4 ) ) / 100000;
                    break;

                case 'sRGB': // Standard RGB Color Space
                    $thisfile_png_chunk_type_text['header']                 = $chunk;
                    $thisfile_png_chunk_type_text['reindering_intent']      = Helper::BigEndian2Int( $chunk['data'] );
                    $thisfile_png_chunk_type_text['reindering_intent_text'] = $this->PNGsRGBintentLookup( $thisfile_png_chunk_type_text['reindering_intent'] );
                    break;

                case 'iCCP': // Embedded ICC Profile
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $profilename, $compressiondata ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['profile_name']        = $profilename;
                    $thisfile_png_chunk_type_text['compression_method']  = Helper::BigEndian2Int( substr( $compressiondata, 0, 1 ) );
                    $thisfile_png_chunk_type_text['compression_profile'] = substr( $compressiondata, 1 );

                    $thisfile_png_chunk_type_text['compression_method_text'] = $this->PNGcompressionMethodLookup( $thisfile_png_chunk_type_text['compression_method'] );
                    break;

                case 'tEXt': // Textual Data
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $keyword, $text ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['keyword'] = $keyword;
                    $thisfile_png_chunk_type_text['text']    = $text;

                    $thisfile_png['comments'][$thisfile_png_chunk_type_text['keyword']][] = $thisfile_png_chunk_type_text['text'];
                    break;

                case 'zTXt': // Compressed Textual Data
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $keyword, $otherdata ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['keyword']                 = $keyword;
                    $thisfile_png_chunk_type_text['compression_method']      = Helper::BigEndian2Int( substr( $otherdata, 0, 1 ) );
                    $thisfile_png_chunk_type_text['compressed_text']         = substr( $otherdata, 1 );
                    $thisfile_png_chunk_type_text['compression_method_text'] = $this->PNGcompressionMethodLookup( $thisfile_png_chunk_type_text['compression_method'] );
                    switch ( $thisfile_png_chunk_type_text['compression_method'] )
                    {
                        case 0:
                            $thisfile_png_chunk_type_text['text'] = gzuncompress( $thisfile_png_chunk_type_text['compressed_text'] );
                            break;

                        default:
                            // unknown compression method
                            break;
                    }

                    if ( isset( $thisfile_png_chunk_type_text['text'] ) )
                    {
                        $thisfile_png['comments'][$thisfile_png_chunk_type_text['keyword']][] = $thisfile_png_chunk_type_text['text'];
                    }
                    break;

                case 'iTXt': // International Textual Data
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $keyword, $otherdata ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['keyword']                 = $keyword;
                    $thisfile_png_chunk_type_text['compression']             = (bool) Helper::BigEndian2Int( substr( $otherdata, 0, 1 ) );
                    $thisfile_png_chunk_type_text['compression_method']      = Helper::BigEndian2Int( substr( $otherdata, 1, 1 ) );
                    $thisfile_png_chunk_type_text['compression_method_text'] = $this->PNGcompressionMethodLookup( $thisfile_png_chunk_type_text['compression_method'] );
                    list( $languagetag, $translatedkeyword, $text ) = explode( "\x00", substr( $otherdata, 2 ), 3 );
                    $thisfile_png_chunk_type_text['language_tag']       = $languagetag;
                    $thisfile_png_chunk_type_text['translated_keyword'] = $translatedkeyword;

                    if ( $thisfile_png_chunk_type_text['compression'] )
                    {
                        switch ( $thisfile_png_chunk_type_text['compression_method'] )
                        {
                            case 0:
                                $thisfile_png_chunk_type_text['text'] = gzuncompress( $text );
                                break;

                            default:
                                // unknown compression method
                                break;
                        }
                    }
                    else
                    {
                        $thisfile_png_chunk_type_text['text'] = $text;
                    }

                    if ( isset( $thisfile_png_chunk_type_text['text'] ) )
                    {
                        $thisfile_png['comments'][$thisfile_png_chunk_type_text['keyword']][] = $thisfile_png_chunk_type_text['text'];
                    }
                    break;

                case 'bKGD': // Background Color
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    switch ( $thisfile_png['IHDR']['raw']['color_type'] )
                    {
                        case 0:
                        case 4:
                            $thisfile_png_chunk_type_text['background_gray'] = Helper::BigEndian2Int( $chunk['data'] );
                            break;

                        case 2:
                        case 6:
                            $thisfile_png_chunk_type_text['background_red']   = Helper::BigEndian2Int( substr( $chunk['data'], 0 * $thisfile_png['IHDR']['raw']['bit_depth'], $thisfile_png['IHDR']['raw']['bit_depth'] ) );
                            $thisfile_png_chunk_type_text['background_green'] = Helper::BigEndian2Int( substr( $chunk['data'], 1 * $thisfile_png['IHDR']['raw']['bit_depth'], $thisfile_png['IHDR']['raw']['bit_depth'] ) );
                            $thisfile_png_chunk_type_text['background_blue']  = Helper::BigEndian2Int( substr( $chunk['data'], 2 * $thisfile_png['IHDR']['raw']['bit_depth'], $thisfile_png['IHDR']['raw']['bit_depth'] ) );
                            break;

                        case 3:
                            $thisfile_png_chunk_type_text['background_index'] = Helper::BigEndian2Int( $chunk['data'] );
                            break;

                        default:
                            break;
                    }
                    break;

                case 'pHYs': // Physical Pixel Dimensions
                    $thisfile_png_chunk_type_text['header']            = $chunk;
                    $thisfile_png_chunk_type_text['pixels_per_unit_x'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 4 ) );
                    $thisfile_png_chunk_type_text['pixels_per_unit_y'] = Helper::BigEndian2Int( substr( $chunk['data'], 4, 4 ) );
                    $thisfile_png_chunk_type_text['unit_specifier']    = Helper::BigEndian2Int( substr( $chunk['data'], 8, 1 ) );
                    $thisfile_png_chunk_type_text['unit']              = $this->PNGpHYsUnitLookup( $thisfile_png_chunk_type_text['unit_specifier'] );
                    break;

                case 'sBIT': // Significant Bits
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    switch ( $thisfile_png['IHDR']['raw']['color_type'] )
                    {
                        case 0:
                            $thisfile_png_chunk_type_text['significant_bits_gray'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                            break;

                        case 2:
                        case 3:
                            $thisfile_png_chunk_type_text['significant_bits_red']   = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_green'] = Helper::BigEndian2Int( substr( $chunk['data'], 1, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_blue']  = Helper::BigEndian2Int( substr( $chunk['data'], 2, 1 ) );
                            break;

                        case 4:
                            $thisfile_png_chunk_type_text['significant_bits_gray']  = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_alpha'] = Helper::BigEndian2Int( substr( $chunk['data'], 1, 1 ) );
                            break;

                        case 6:
                            $thisfile_png_chunk_type_text['significant_bits_red']   = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_green'] = Helper::BigEndian2Int( substr( $chunk['data'], 1, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_blue']  = Helper::BigEndian2Int( substr( $chunk['data'], 2, 1 ) );
                            $thisfile_png_chunk_type_text['significant_bits_alpha'] = Helper::BigEndian2Int( substr( $chunk['data'], 3, 1 ) );
                            break;

                        default:
                            break;
                    }
                    break;

                case 'sPLT': // Suggested Palette
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $palettename, $otherdata ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['palette_name']      = $palettename;
                    $sPLToffset                                        = 0;
                    $thisfile_png_chunk_type_text['sample_depth_bits'] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, 1 ) );
                    $sPLToffset += 1;
                    $thisfile_png_chunk_type_text['sample_depth_bytes'] = $thisfile_png_chunk_type_text['sample_depth_bits'] / 8;
                    $paletteCounter                                     = 0;
                    while ( $sPLToffset < strlen( $otherdata ) )
                    {
                        $thisfile_png_chunk_type_text['red'][$paletteCounter] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, $thisfile_png_chunk_type_text['sample_depth_bytes'] ) );
                        $sPLToffset += $thisfile_png_chunk_type_text['sample_depth_bytes'];
                        $thisfile_png_chunk_type_text['green'][$paletteCounter] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, $thisfile_png_chunk_type_text['sample_depth_bytes'] ) );
                        $sPLToffset += $thisfile_png_chunk_type_text['sample_depth_bytes'];
                        $thisfile_png_chunk_type_text['blue'][$paletteCounter] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, $thisfile_png_chunk_type_text['sample_depth_bytes'] ) );
                        $sPLToffset += $thisfile_png_chunk_type_text['sample_depth_bytes'];
                        $thisfile_png_chunk_type_text['alpha'][$paletteCounter] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, $thisfile_png_chunk_type_text['sample_depth_bytes'] ) );
                        $sPLToffset += $thisfile_png_chunk_type_text['sample_depth_bytes'];
                        $thisfile_png_chunk_type_text['frequency'][$paletteCounter] = Helper::BigEndian2Int( substr( $otherdata, $sPLToffset, 2 ) );
                        $sPLToffset += 2;
                        ++$paletteCounter;
                    }
                    break;

                case 'hIST': // Palette Histogram
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    $hISTcounter                            = 0;
                    while ( $hISTcounter < strlen( $chunk['data'] ) )
                    {
                        $thisfile_png_chunk_type_text[$hISTcounter] = Helper::BigEndian2Int( substr( $chunk['data'], $hISTcounter / 2, 2 ) );
                        $hISTcounter += 2;
                    }
                    break;

                case 'tIME': // Image Last-Modification Time
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    $thisfile_png_chunk_type_text['year']   = Helper::BigEndian2Int( substr( $chunk['data'], 0, 2 ) );
                    $thisfile_png_chunk_type_text['month']  = Helper::BigEndian2Int( substr( $chunk['data'], 2, 1 ) );
                    $thisfile_png_chunk_type_text['day']    = Helper::BigEndian2Int( substr( $chunk['data'], 3, 1 ) );
                    $thisfile_png_chunk_type_text['hour']   = Helper::BigEndian2Int( substr( $chunk['data'], 4, 1 ) );
                    $thisfile_png_chunk_type_text['minute'] = Helper::BigEndian2Int( substr( $chunk['data'], 5, 1 ) );
                    $thisfile_png_chunk_type_text['second'] = Helper::BigEndian2Int( substr( $chunk['data'], 6, 1 ) );
                    $thisfile_png_chunk_type_text['unix']   = gmmktime( $thisfile_png_chunk_type_text['hour'], $thisfile_png_chunk_type_text['minute'], $thisfile_png_chunk_type_text['second'], $thisfile_png_chunk_type_text['month'], $thisfile_png_chunk_type_text['day'], $thisfile_png_chunk_type_text['year'] );
                    break;

                case 'oFFs': // Image Offset
                    $thisfile_png_chunk_type_text['header']         = $chunk;
                    $thisfile_png_chunk_type_text['position_x']     = Helper::BigEndian2Int( substr( $chunk['data'], 0, 4 ), false, true );
                    $thisfile_png_chunk_type_text['position_y']     = Helper::BigEndian2Int( substr( $chunk['data'], 4, 4 ), false, true );
                    $thisfile_png_chunk_type_text['unit_specifier'] = Helper::BigEndian2Int( substr( $chunk['data'], 8, 1 ) );
                    $thisfile_png_chunk_type_text['unit']           = $this->PNGoFFsUnitLookup( $thisfile_png_chunk_type_text['unit_specifier'] );
                    break;

                case 'pCAL': // Calibration Of Pixel Values
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    list( $calibrationname, $otherdata ) = explode( "\x00", $chunk['data'], 2 );
                    $thisfile_png_chunk_type_text['calibration_name'] = $calibrationname;
                    $pCALoffset                                       = 0;
                    $thisfile_png_chunk_type_text['original_zero']    = Helper::BigEndian2Int( substr( $chunk['data'], $pCALoffset, 4 ), false, true );
                    $pCALoffset += 4;
                    $thisfile_png_chunk_type_text['original_max'] = Helper::BigEndian2Int( substr( $chunk['data'], $pCALoffset, 4 ), false, true );
                    $pCALoffset += 4;
                    $thisfile_png_chunk_type_text['equation_type'] = Helper::BigEndian2Int( substr( $chunk['data'], $pCALoffset, 1 ) );
                    $pCALoffset += 1;
                    $thisfile_png_chunk_type_text['equation_type_text'] = $this->PNGpCALequationTypeLookup( $thisfile_png_chunk_type_text['equation_type'] );
                    $thisfile_png_chunk_type_text['parameter_count']    = Helper::BigEndian2Int( substr( $chunk['data'], $pCALoffset, 1 ) );
                    $pCALoffset += 1;
                    $thisfile_png_chunk_type_text['parameters'] = explode( "\x00", substr( $chunk['data'], $pCALoffset ) );
                    break;

                case 'sCAL': // Physical Scale Of Image Subject
                    $thisfile_png_chunk_type_text['header']         = $chunk;
                    $thisfile_png_chunk_type_text['unit_specifier'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                    $thisfile_png_chunk_type_text['unit']           = $this->PNGsCALUnitLookup( $thisfile_png_chunk_type_text['unit_specifier'] );
                    list( $pixelwidth, $pixelheight ) = explode( "\x00", substr( $chunk['data'], 1 ) );
                    $thisfile_png_chunk_type_text['pixel_width']  = $pixelwidth;
                    $thisfile_png_chunk_type_text['pixel_height'] = $pixelheight;
                    break;

                case 'gIFg': // GIF Graphic Control Extension
                    $gIFgCounter = 0;
                    if ( isset( $thisfile_png_chunk_type_text ) && is_array( $thisfile_png_chunk_type_text ) )
                    {
                        $gIFgCounter = count( $thisfile_png_chunk_type_text );
                    }
                    $thisfile_png_chunk_type_text[$gIFgCounter]['header']          = $chunk;
                    $thisfile_png_chunk_type_text[$gIFgCounter]['disposal_method'] = Helper::BigEndian2Int( substr( $chunk['data'], 0, 1 ) );
                    $thisfile_png_chunk_type_text[$gIFgCounter]['user_input_flag'] = Helper::BigEndian2Int( substr( $chunk['data'], 1, 1 ) );
                    $thisfile_png_chunk_type_text[$gIFgCounter]['delay_time']      = Helper::BigEndian2Int( substr( $chunk['data'], 2, 2 ) );
                    break;

                case 'gIFx': // GIF Application Extension
                    $gIFxCounter = 0;
                    if ( isset( $thisfile_png_chunk_type_text ) && is_array( $thisfile_png_chunk_type_text ) )
                    {
                        $gIFxCounter = count( $thisfile_png_chunk_type_text );
                    }
                    $thisfile_png_chunk_type_text[$gIFxCounter]['header']                 = $chunk;
                    $thisfile_png_chunk_type_text[$gIFxCounter]['application_identifier'] = substr( $chunk['data'], 0, 8 );
                    $thisfile_png_chunk_type_text[$gIFxCounter]['authentication_code']    = substr( $chunk['data'], 8, 3 );
                    $thisfile_png_chunk_type_text[$gIFxCounter]['application_data']       = substr( $chunk['data'], 11 );
                    break;

                case 'IDAT': // Image Data
                    $idatinformationfieldindex = 0;
                    if ( isset( $thisfile_png['IDAT'] ) && is_array( $thisfile_png['IDAT'] ) )
                    {
                        $idatinformationfieldindex = count( $thisfile_png['IDAT'] );
                    }
                    unset( $chunk['data'] );
                    $thisfile_png_chunk_type_text[$idatinformationfieldindex]['header'] = $chunk;
                    break;

                case 'IEND': // Image Trailer
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    break;

                default:
                    //unset($chunk['data']);
                    $thisfile_png_chunk_type_text['header'] = $chunk;
                    $info['warning'][]                      = 'Unhandled chunk type: ' . $chunk['type_text'];
                    break;
            }
        }

        return true;
    }

    /**
     * @staticvar array $PNGsRGBintentLookup
     *
     * @param  type $sRGB
     *
     * @return type
     */
    public function PNGsRGBintentLookup( $sRGB )
    {
        static $PNGsRGBintentLookup = [
            0 => 'Perceptual',
            1 => 'Relative colorimetric',
            2 => 'Saturation',
            3 => 'Absolute colorimetric',
        ];

        return ( isset( $PNGsRGBintentLookup[$sRGB] ) ? $PNGsRGBintentLookup[$sRGB] : 'invalid' );
    }

    /**
     * @staticvar array $PNGcompressionMethodLookup
     *
     * @param  type $compressionmethod
     *
     * @return type
     */
    public function PNGcompressionMethodLookup( $compressionmethod )
    {
        static $PNGcompressionMethodLookup = [
            0 => 'deflate/inflate',
        ];

        return ( isset( $PNGcompressionMethodLookup[$compressionmethod] ) ? $PNGcompressionMethodLookup[$compressionmethod] : 'invalid' );
    }

    /**
     * @staticvar array $PNGpHYsUnitLookup
     *
     * @param  type $unitid
     *
     * @return type
     */
    public function PNGpHYsUnitLookup( $unitid )
    {
        static $PNGpHYsUnitLookup = [
            0 => 'unknown',
            1 => 'meter',
        ];

        return ( isset( $PNGpHYsUnitLookup[$unitid] ) ? $PNGpHYsUnitLookup[$unitid] : 'invalid' );
    }

    /**
     * @staticvar array $PNGoFFsUnitLookup
     *
     * @param  type $unitid
     *
     * @return type
     */
    public function PNGoFFsUnitLookup( $unitid )
    {
        static $PNGoFFsUnitLookup = [
            0 => 'pixel',
            1 => 'micrometer',
        ];

        return ( isset( $PNGoFFsUnitLookup[$unitid] ) ? $PNGoFFsUnitLookup[$unitid] : 'invalid' );
    }

    /**
     * @staticvar array $PNGpCALequationTypeLookup
     *
     * @param  type $equationtype
     *
     * @return type
     */
    public function PNGpCALequationTypeLookup( $equationtype )
    {
        static $PNGpCALequationTypeLookup = [
            0 => 'Linear mapping',
            1 => 'Base-e exponential mapping',
            2 => 'Arbitrary-base exponential mapping',
            3 => 'Hyperbolic mapping',
        ];

        return ( isset( $PNGpCALequationTypeLookup[$equationtype] ) ? $PNGpCALequationTypeLookup[$equationtype] : 'invalid' );
    }

    /**
     * @staticvar array $PNGsCALUnitLookup
     *
     * @param  type $unitid
     *
     * @return type
     */
    public function PNGsCALUnitLookup( $unitid )
    {
        static $PNGsCALUnitLookup = [
            0 => 'meter',
            1 => 'radian',
        ];

        return ( isset( $PNGsCALUnitLookup[$unitid] ) ? $PNGsCALUnitLookup[$unitid] : 'invalid' );
    }

    /**
     * @param  type $color_type
     * @param  type $bit_depth
     *
     * @return bool
     */
    public function IHDRcalculateBitsPerSample( $color_type, $bit_depth )
    {
        switch ( $color_type )
        {
            case 0: // Each pixel is a grayscale sample.

                return $bit_depth;
                break;

            case 2: // Each pixel is an R,G,B triple

                return 3 * $bit_depth;
                break;

            case 3: // Each pixel is a palette index; a PLTE chunk must appear.

                return $bit_depth;
                break;

            case 4: // Each pixel is a grayscale sample, followed by an alpha sample.

                return 2 * $bit_depth;
                break;

            case 6: // Each pixel is an R,G,B triple, followed by an alpha sample.

                return 4 * $bit_depth;
                break;
        }

        return false;
    }
}
