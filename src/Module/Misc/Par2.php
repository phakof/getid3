<?php

namespace arabcoders\getid3\Module\Misc;

use arabcoders\getid3\Handler\BaseHandler;
use arabcoders\getid3\Interfaces\ModuleInterface;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.par2.php                                        //
// module for analyzing PAR2 files                             //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for analyzing PAR2 files
 *
 * @author James Heinrich <info@getid3.org>
 *
 * @link   http://getid3.sourceforge.net
 * @link   http://www.getid3.org
 */
class Par2 extends BaseHandler implements ModuleInterface
{
    /**
     * @return bool
     */
    public function analyze()
    {
        $info = &$this->getid3->info;

        $info['fileformat'] = 'par2';

        $info['error'][] = 'PAR2 parsing not enabled in this version of GetId3Core()';

        return false;
    }
}
