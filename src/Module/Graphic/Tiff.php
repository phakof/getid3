<?php

namespace arabcoders\getid3\Module\Graphic;

use arabcoders\getid3\Handler\BaseHandler;
use arabcoders\getid3\Lib\Helper;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.tiff.php                                     //
// module for analyzing TIFF files                             //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for analyzing TIFF files
 *
 * @author James Heinrich <info@getid3.org>
 *
 * @link   http://getid3.sourceforge.net
 * @link   http://www.getid3.org
 */
class Tiff extends BaseHandler
{
    /**
     * @return bool
     */
    public function analyze()
    {
        $info = &$this->getid3->info;

        fseek( $this->getid3->fp, $info['avdataoffset'], SEEK_SET );
        $TIFFheader = fread( $this->getid3->fp, 4 );

        switch ( substr( $TIFFheader, 0, 2 ) )
        {
            case 'II':
                $info['tiff']['byte_order'] = 'Intel';
                break;
            case 'MM':
                $info['tiff']['byte_order'] = 'Motorola';
                break;
            default:
                $info['error'][] = 'Invalid TIFF byte order identifier (' . substr( $TIFFheader, 0, 2 ) . ') at offset ' . $info['avdataoffset'];

                return false;
                break;
        }

        $info['fileformat']          = 'tiff';
        $info['video']['dataformat'] = 'tiff';
        $info['video']['lossless']   = true;
        $info['tiff']['ifd']         = [];
        $CurrentIFD                  = [];

        $FieldTypeByteLength = [ 1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8 ];

        $nextIFDoffset = $this->TIFFendian2Int( fread( $this->getid3->fp, 4 ), $info['tiff']['byte_order'] );

        while ( $nextIFDoffset > 0 )
        {
            $CurrentIFD['offset'] = $nextIFDoffset;

            fseek( $this->getid3->fp, $info['avdataoffset'] + $nextIFDoffset, SEEK_SET );
            $CurrentIFD['fieldcount'] = $this->TIFFendian2Int( fread( $this->getid3->fp, 2 ), $info['tiff']['byte_order'] );

            for ( $i = 0; $i < $CurrentIFD['fieldcount']; ++$i )
            {
                $CurrentIFD['fields'][$i]['raw']['tag']    = $this->TIFFendian2Int( fread( $this->getid3->fp, 2 ), $info['tiff']['byte_order'] );
                $CurrentIFD['fields'][$i]['raw']['type']   = $this->TIFFendian2Int( fread( $this->getid3->fp, 2 ), $info['tiff']['byte_order'] );
                $CurrentIFD['fields'][$i]['raw']['length'] = $this->TIFFendian2Int( fread( $this->getid3->fp, 4 ), $info['tiff']['byte_order'] );
                $CurrentIFD['fields'][$i]['raw']['offset'] = fread( $this->getid3->fp, 4 );

                switch ( $CurrentIFD['fields'][$i]['raw']['type'] )
                {
                    case 1: // BYTE  An 8-bit unsigned integer.
                        if ( $CurrentIFD['fields'][$i]['raw']['length'] <= 4 )
                        {
                            $CurrentIFD['fields'][$i]['value'] = $this->TIFFendian2Int( substr( $CurrentIFD['fields'][$i]['raw']['offset'], 0, 1 ), $info['tiff']['byte_order'] );
                        }
                        else
                        {
                            $CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int( $CurrentIFD['fields'][$i]['raw']['offset'], $info['tiff']['byte_order'] );
                        }
                        break;

                    case 2: // ASCII 8-bit bytes  that store ASCII codes; the last byte must be null.
                        if ( $CurrentIFD['fields'][$i]['raw']['length'] <= 4 )
                        {
                            $CurrentIFD['fields'][$i]['value'] = substr( $CurrentIFD['fields'][$i]['raw']['offset'], 3 );
                        }
                        else
                        {
                            $CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int( $CurrentIFD['fields'][$i]['raw']['offset'], $info['tiff']['byte_order'] );
                        }
                        break;

                    case 3: // SHORT A 16-bit (2-byte) unsigned integer.
                        if ( $CurrentIFD['fields'][$i]['raw']['length'] <= 2 )
                        {
                            $CurrentIFD['fields'][$i]['value'] = $this->TIFFendian2Int( substr( $CurrentIFD['fields'][$i]['raw']['offset'], 0, 2 ), $info['tiff']['byte_order'] );
                        }
                        else
                        {
                            $CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int( $CurrentIFD['fields'][$i]['raw']['offset'], $info['tiff']['byte_order'] );
                        }
                        break;

                    case 4: // LONG  A 32-bit (4-byte) unsigned integer.
                        if ( $CurrentIFD['fields'][$i]['raw']['length'] <= 1 )
                        {
                            $CurrentIFD['fields'][$i]['value'] = $this->TIFFendian2Int( $CurrentIFD['fields'][$i]['raw']['offset'], $info['tiff']['byte_order'] );
                        }
                        else
                        {
                            $CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int( $CurrentIFD['fields'][$i]['raw']['offset'], $info['tiff']['byte_order'] );
                        }
                        break;

                    case 5: // RATIONAL   Two LONG_s:  the first represents the numerator of a fraction, the second the denominator.
                        break;
                }
            }

            $info['tiff']['ifd'][] = $CurrentIFD;
            $CurrentIFD            = [];
            $nextIFDoffset         = $this->TIFFendian2Int( fread( $this->getid3->fp, 4 ), $info['tiff']['byte_order'] );
        }

        foreach ( $info['tiff']['ifd'] as $IFDid => $IFDarray )
        {
            foreach ( $IFDarray['fields'] as $key => $fieldarray )
            {
                switch ( $fieldarray['raw']['tag'] )
                {
                    case 256: // ImageWidth
                    case 257: // ImageLength
                    case 258: // BitsPerSample
                    case 259: // Compression
                        if ( !isset( $fieldarray['value'] ) )
                        {
                            fseek( $this->getid3->fp, $fieldarray['offset'], SEEK_SET );
                            $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = fread( $this->getid3->fp, $fieldarray['raw']['length'] * $FieldTypeByteLength[$fieldarray['raw']['type']] );
                        }
                        break;

                    case 270: // ImageDescription
                    case 271: // Make
                    case 272: // Model
                    case 305: // Software
                    case 306: // DateTime
                    case 315: // Artist
                    case 316: // HostComputer
                        if ( isset( $fieldarray['value'] ) )
                        {
                            $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = $fieldarray['value'];
                        }
                        else
                        {
                            fseek( $this->getid3->fp, $fieldarray['offset'], SEEK_SET );
                            $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = fread( $this->getid3->fp, $fieldarray['raw']['length'] * $FieldTypeByteLength[$fieldarray['raw']['type']] );
                        }
                        break;
                }
                switch ( $fieldarray['raw']['tag'] )
                {
                    case 256: // ImageWidth
                        $info['video']['resolution_x'] = $fieldarray['value'];
                        break;

                    case 257: // ImageLength
                        $info['video']['resolution_y'] = $fieldarray['value'];
                        break;

                    case 258: // BitsPerSample
                        if ( isset( $fieldarray['value'] ) )
                        {
                            $info['video']['bits_per_sample'] = $fieldarray['value'];
                        }
                        else
                        {
                            $info['video']['bits_per_sample'] = 0;
                            for ( $i = 0; $i < $fieldarray['raw']['length']; ++$i )
                            {
                                $info['video']['bits_per_sample'] += $this->TIFFendian2Int( substr( $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'], $i * $FieldTypeByteLength[$fieldarray['raw']['type']], $FieldTypeByteLength[$fieldarray['raw']['type']] ), $info['tiff']['byte_order'] );
                            }
                        }
                        break;

                    case 259: // Compression
                        $info['video']['codec'] = $this->TIFFcompressionMethod( $fieldarray['value'] );
                        break;

                    case 270: // ImageDescription
                    case 271: // Make
                    case 272: // Model
                    case 305: // Software
                    case 306: // DateTime
                    case 315: // Artist
                    case 316: // HostComputer
                        $TIFFcommentName = $this->TIFFcommentName( $fieldarray['raw']['tag'] );
                        if ( isset( $info['tiff']['comments'][$TIFFcommentName] ) )
                        {
                            $info['tiff']['comments'][$TIFFcommentName][] = $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'];
                        }
                        else
                        {
                            $info['tiff']['comments'][$TIFFcommentName] = [ $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] ];
                        }
                        break;

                    default:
                        break;
                }
            }
        }

        return true;
    }

    /**
     * @param  type $bytestring
     * @param  type $byteorder
     *
     * @return bool
     */
    public function TIFFendian2Int( $bytestring, $byteorder )
    {
        if ( $byteorder == 'Intel' )
        {
            return Helper::LittleEndian2Int( $bytestring );
        }
        elseif ( $byteorder == 'Motorola' )
        {
            return Helper::BigEndian2Int( $bytestring );
        }

        return false;
    }

    /**
     * @staticvar array $TIFFcompressionMethod
     *
     * @param  type $id
     *
     * @return type
     */
    public function TIFFcompressionMethod( $id )
    {
        static $TIFFcompressionMethod = [];
        if ( empty( $TIFFcompressionMethod ) )
        {
            $TIFFcompressionMethod = [
                1     => 'Uncompressed',
                2     => 'Huffman',
                3     => 'Fax - CCITT 3',
                5     => 'LZW',
                32773 => 'PackBits',
            ];
        }

        return ( isset( $TIFFcompressionMethod[$id] ) ? $TIFFcompressionMethod[$id] : 'unknown/invalid (' . $id . ')' );
    }

    /**
     * @staticvar array $TIFFcommentName
     *
     * @param  type $id
     *
     * @return type
     */
    public function TIFFcommentName( $id )
    {
        static $TIFFcommentName = [];
        if ( empty( $TIFFcommentName ) )
        {
            $TIFFcommentName = [
                270 => 'imagedescription',
                271 => 'make',
                272 => 'model',
                305 => 'software',
                306 => 'datetime',
                315 => 'artist',
                316 => 'hostcomputer',
            ];
        }

        return ( isset( $TIFFcommentName[$id] ) ? $TIFFcommentName[$id] : 'unknown/invalid (' . $id . ')' );
    }
}