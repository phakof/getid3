<?php

namespace arabcoders\getid3\Module\Audio;

use arabcoders\getid3\GetId3Core;
use arabcoders\getid3\Handler\BaseHandler;
use arabcoders\getid3\Lib\Helper;
use arabcoders\getid3\Module\AudioVideo\Riff;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.la.php                                         //
// module for analyzing LA (LosslessAudio) audio files         //
// dependencies: module.audio.riff.php                         //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for analyzing LA (LosslessAudio) audio files
 *
 * @author James Heinrich <info@getid3.org>
 *
 * @link   http://getid3.sourceforge.net
 * @link   http://www.getid3.org
 */
class La extends BaseHandler
{
    /**
     * @return bool
     */
    public function analyze()
    {
        $info = &$this->getid3->info;

        $offset = 0;
        fseek( $this->getid3->fp, $info['avdataoffset'], SEEK_SET );
        $rawdata = fread( $this->getid3->fp, $this->getid3->fread_buffer_size() );

        switch ( substr( $rawdata, $offset, 4 ) )
        {
            case 'LA02':
            case 'LA03':
            case 'LA04':
                $info['fileformat']          = 'la';
                $info['audio']['dataformat'] = 'la';
                $info['audio']['lossless']   = true;

                $info['la']['version_major'] = (int) substr( $rawdata, $offset + 2, 1 );
                $info['la']['version_minor'] = (int) substr( $rawdata, $offset + 3, 1 );
                $info['la']['version']       = (float) $info['la']['version_major'] + ( $info['la']['version_minor'] / 10 );
                $offset += 4;

                $info['la']['uncompressed_size'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;
                if ( $info['la']['uncompressed_size'] == 0 )
                {
                    $info['error'][] = 'Corrupt LA file: uncompressed_size == zero';

                    return false;
                }

                $WAVEchunk = substr( $rawdata, $offset, 4 );
                if ( $WAVEchunk !== 'WAVE' )
                {
                    $info['error'][] = 'Expected "WAVE" (' . Helper::PrintHexBytes( 'WAVE' ) . ') at offset ' . $offset . ', found "' . $WAVEchunk . '" (' . Helper::PrintHexBytes( $WAVEchunk ) . ') instead.';

                    return false;
                }
                $offset += 4;

                $info['la']['fmt_size'] = 24;
                if ( $info['la']['version'] >= 0.3 )
                {
                    $info['la']['fmt_size']    = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                    $info['la']['header_size'] = 49 + $info['la']['fmt_size'] - 24;
                    $offset += 4;
                }
                else
                {

                    // version 0.2 didn't support additional data blocks
                    $info['la']['header_size'] = 41;
                }

                $fmt_chunk = substr( $rawdata, $offset, 4 );
                if ( $fmt_chunk !== 'fmt ' )
                {
                    $info['error'][] = 'Expected "fmt " (' . Helper::PrintHexBytes( 'fmt ' ) . ') at offset ' . $offset . ', found "' . $fmt_chunk . '" (' . Helper::PrintHexBytes( $fmt_chunk ) . ') instead.';

                    return false;
                }
                $offset += 4;
                $fmt_size = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;

                $info['la']['raw']['format'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 2 ) );
                $offset += 2;

                $info['la']['channels'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 2 ) );
                $offset += 2;
                if ( $info['la']['channels'] == 0 )
                {
                    $info['error'][] = 'Corrupt LA file: channels == zero';

                    return false;
                }

                $info['la']['sample_rate'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;
                if ( $info['la']['sample_rate'] == 0 )
                {
                    $info['error'][] = 'Corrupt LA file: sample_rate == zero';

                    return false;
                }

                $info['la']['bytes_per_second'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;
                $info['la']['bytes_per_sample'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 2 ) );
                $offset += 2;
                $info['la']['bits_per_sample'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 2 ) );
                $offset += 2;

                $info['la']['samples'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;

                $info['la']['raw']['flags'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 1 ) );
                $offset += 1;
                $info['la']['flags']['seekable'] = (bool) ( $info['la']['raw']['flags'] & 0x01 );
                if ( $info['la']['version'] >= 0.4 )
                {
                    $info['la']['flags']['high_compression'] = (bool) ( $info['la']['raw']['flags'] & 0x02 );
                }

                $info['la']['original_crc'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                $offset += 4;

                // mikeØbevin*de
                // Basically, the blocksize/seekevery are 61440/19 in La0.4 and 73728/16
                // in earlier versions. A seekpoint is added every blocksize * seekevery
                // samples, so 4 * int(totalSamples / (blockSize * seekEvery)) should
                // give the number of bytes used for the seekpoints. Of course, if seeking
                // is disabled, there are no seekpoints stored.
                if ( $info['la']['version'] >= 0.4 )
                {
                    $info['la']['blocksize'] = 61440;
                    $info['la']['seekevery'] = 19;
                }
                else
                {
                    $info['la']['blocksize'] = 73728;
                    $info['la']['seekevery'] = 16;
                }

                $info['la']['seekpoint_count'] = 0;
                if ( $info['la']['flags']['seekable'] )
                {
                    $info['la']['seekpoint_count'] = floor( $info['la']['samples'] / ( $info['la']['blocksize'] * $info['la']['seekevery'] ) );

                    for ( $i = 0; $i < $info['la']['seekpoint_count']; ++$i )
                    {
                        $info['la']['seekpoints'][] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                        $offset += 4;
                    }
                }

                if ( $info['la']['version'] >= 0.3 )
                {

                    // Following the main header information, the program outputs all of the
                    // seekpoints. Following these is what I called the 'footer start',
                    // i.e. the position immediately after the La audio data is finished.
                    $info['la']['footerstart'] = Helper::LittleEndian2Int( substr( $rawdata, $offset, 4 ) );
                    $offset += 4;

                    if ( $info['la']['footerstart'] > $info['filesize'] )
                    {
                        $info['warning'][]         = 'FooterStart value points to offset ' . $info['la']['footerstart'] . ' which is beyond end-of-file (' . $info['filesize'] . ')';
                        $info['la']['footerstart'] = $info['filesize'];
                    }
                }
                else
                {

                    // La v0.2 didn't have FooterStart value
                    $info['la']['footerstart'] = $info['avdataend'];
                }

                if ( $info['la']['footerstart'] < $info['avdataend'] )
                {
                    if ( $RIFFtempfilename = tempnam( GetId3Core::getTempDir(), 'id3' ) )
                    {
                        if ( $RIFF_fp = fopen( $RIFFtempfilename, 'w+b' ) )
                        {
                            $RIFFdata = 'WAVE';
                            if ( $info['la']['version'] == 0.2 )
                            {
                                $RIFFdata .= substr( $rawdata, 12, 24 );
                            }
                            else
                            {
                                $RIFFdata .= substr( $rawdata, 16, 24 );
                            }
                            if ( $info['la']['footerstart'] < $info['avdataend'] )
                            {
                                fseek( $this->getid3->fp, $info['la']['footerstart'], SEEK_SET );
                                $RIFFdata .= fread( $this->getid3->fp, $info['avdataend'] - $info['la']['footerstart'] );
                            }
                            $RIFFdata = 'RIFF' . Helper::LittleEndian2String( strlen( $RIFFdata ), 4, false ) . $RIFFdata;
                            fwrite( $RIFF_fp, $RIFFdata, strlen( $RIFFdata ) );
                            fclose( $RIFF_fp );

                            $getid3_temp = new GetId3Core();
                            $getid3_temp->openfile( $RIFFtempfilename );
                            $getid3_riff = new Riff( $getid3_temp );
                            $getid3_riff->analyze();

                            if ( empty( $getid3_temp->info['error'] ) )
                            {
                                $info['riff'] = $getid3_temp->info['riff'];
                            }
                            else
                            {
                                $info['warning'][] = 'Error parsing RIFF portion of La file: ' . implode( $getid3_temp->info['error'] );
                            }
                            unset( $getid3_temp, $getid3_riff );
                        }
                        unlink( $RIFFtempfilename );
                    }
                }

                // $info['avdataoffset'] should be zero to begin with, but just in case it's not, include the addition anyway
                $info['avdataend']    = $info['avdataoffset'] + $info['la']['footerstart'];
                $info['avdataoffset'] = $info['avdataoffset'] + $offset;

                //$info['la']['codec']                = RIFFwFormatTagLookup($info['la']['raw']['format']);
                $info['la']['compression_ratio'] = (float) ( ( $info['avdataend'] - $info['avdataoffset'] ) / $info['la']['uncompressed_size'] );
                $info['playtime_seconds']        = (float) ( $info['la']['samples'] / $info['la']['sample_rate'] ) / $info['la']['channels'];
                if ( $info['playtime_seconds'] == 0 )
                {
                    $info['error'][] = 'Corrupt LA file: playtime_seconds == zero';

                    return false;
                }

                $info['audio']['bitrate'] = ( $info['avdataend'] - $info['avdataoffset'] ) * 8 / $info['playtime_seconds'];
                //$info['audio']['codec']              = $info['la']['codec'];
                $info['audio']['bits_per_sample'] = $info['la']['bits_per_sample'];
                break;

            default:
                if ( substr( $rawdata, $offset, 2 ) == 'LA' )
                {
                    $info['error'][] = 'This version of GetId3Core() [' . $this->getid3->version() . '] does not support LA version ' . substr( $rawdata, $offset + 2, 1 ) . '.' . substr( $rawdata, $offset + 3, 1 ) . ' which this appears to be - check http://getid3.sourceforge.net for updates.';
                }
                else
                {
                    $info['error'][] = 'Not a LA (Lossless-Audio) file';
                }

                return false;
                break;
        }

        $info['audio']['channels']    = $info['la']['channels'];
        $info['audio']['sample_rate'] = (int) $info['la']['sample_rate'];
        $info['audio']['encoder']     = 'LA v' . $info['la']['version'];

        return true;
    }
}
