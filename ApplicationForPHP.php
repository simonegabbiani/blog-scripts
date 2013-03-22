<?php

// SHMApplicationData.php (part of ApplicationForPHP)
// (c) Simone Gabbiani. Release under the GNU General Public License 2.0

##
##        $Application = new SHMApplicationData('MyPetStore);
##
##        $Application->Set( 'foo', 'bar' );
##        echo $Application->Get( 'foo' );
##
##        $Application->Lock();
##        if (($i = $Application->Get('Counter')) == NULL) $i = 0;
##          else $i++;
##        $Application->Set( 'Counter', $i );
##        $Application->Unlock();
##
##


//require_once "IApplicationData.php";

if (!defined('SSM_ID_KEY'))
    //default fixed number for all applications shared | getmyinode()
    define('SSM_ID_KEY', fileinode('/'));
   
if (!defined('IAD_APPLICATION_SERVER_SHMID'))
    define('IAD_APPLICATION_SERVER_SHMID', SSM_ID_KEY);
   
if (!defined('IAD_APPLICATION_SHM_SIZE'))
    define('IAD_APPLICATION_SHM_SIZE', 0xFFFF);


class SHMUnsupportedException extends Exception {
}


/**
 * Description of SHMApplicationData
 *
 * @author Simone Gabbiani
 */
class SHMApplicationData //implements IApplicationData
{
   
    // keep opened the memory segment for entire instance
    // avoiding reopen more times
    private $SHMDataBlockId = 0;
    private $ContextID = IAD_APPLICATION_SERVER_SHMID;
    private $userLockSemId = 0;
   
    function __construct( $Context = IAD_AUTOMATIC ) {
        if (!function_exists("shmop_open")) {
            throw new SHMUnsupportedException("check --enable-shmop in your php.ini");
        }
        // seleziona un contesto
        // (ogni contesto punta a un diverso segmento di memoria condivisa)
        if (!IAD_IS_MSWIN) {
            if ($Context == IAD_AUTOMATIC)
                $this->ContextID = ftok( __FILE__, 'a' );
            else if ($Context != IAD_SERVER) // ftok non disponibile su Windows
                if (function_exists( 'ftok' ) && ($this->ContextID = ftok( $Context, 'a' )) == 0) {
                    user_error( 'Bad context name (use a real file path)', E_USER_ERROR );
                    $this->ContextID = IAD_APPLICATION_SERVER_SHMID;
                }
        }
        else if ($Context !== IAD_SERVER) {
            user_error( "Only IAD_SERVER context supported on Windows platforms, ignoring '$Context'", E_USER_NOTICE );
            $Context = IAD_SERVER;
        }
        if ($Context != IAD_SERVER && isset($GLOBALS['SAVEAPPCONTEXTS']) && $GLOBALS['SAVEAPPCONTEXTS']) {
            $ServerApplication = new ApplicationData( IAD_SERVER );
            $ServerApplication->Set( '$$['.$this->ContextID, $Context );
        }
    }
   

    function __destruct() {
        if ($this->SHMDataBlockId != 0)
            shmop_close( $this->SHMDataBlockId );
    }
   

    private function set_shmapp( $buffer ) {
        if ($this->SHMDataBlockId == 0) {
            if (($this->SHMDataBlockId = shmop_open( $this->ContextID, 'w', 0, 0 )) == false) {
                $this->SHMDataBlockId = shmop_open( $this->ContextID, 'c', 0664, IAD_APPLICATION_SHM_SIZE );
                if (!$this->SHMDataBlockId) {
                   user_error( 'Couldn\'t create shared memory segment', E_USER_ERROR );
                   return NULL;
                }
            }
        }
        $l = strlen((string)( $s = serialize( $buffer ) ));
        if ($l >= IAD_APPLICATION_SHM_SIZE) {
            user_error( 'ApplicationData data out of memory (' . $this->ContextID . ')', E_USER_ERROR );
            return NULL;
        }
        if (shmop_write( $this->SHMDataBlockId, $s, 0 ) != $l)
            user_error( "Could not write data into shared memory segment", E_USER_ERROR );
        return $buffer;
    }
   
   
    private function get_shmapp() {
        if ($this->SHMDataBlockId == 0) {
            if (($this->SHMDataBlockId = shmop_open( $this->ContextID, 'w', 0, 0 )) == NULL)
                return NULL;
            if ($this->SHMDataBlockId == 0) {
               user_error( 'Couldn\'t open shared memory segment', E_USER_ERROR );
               return NULL;
            }
        }
        $buffer = unserialize( shmop_read( $this->SHMDataBlockId, 0, 0xFFFF ) );
        return $buffer;
    }
   

    public function Exists( $name ) {
        if (!($data = $this->get_shmapp()))
            return false;
        return isset( $data[ $name ] );
    }
   
   
    public function Set( $name, $value ) {
        $SemId = 0;
        if ($this->userLockSemId == 0 && !IAD_IS_MSWIN) {
            if (($SemId = sem_get( $this->ContextID, 0664, false )) == false)
                user_error( 'Couldn\'t open semaphore', E_USER_ERROR );
            sem_acquire( $SemId );
        }
        if (!($data = $this->get_shmapp())) {
            $data = array();
        }
        $data[ $name ] = $value;
        $this->set_shmapp( $data );
        if ($this->userLockSemId == 0 && !IAD_IS_MSWIN)
            sem_release( $SemId );
    }
   
   
    public function Get( $name ) {
        $SemId = 0;
        if ($this->userLockSemId == 0 && !IAD_IS_MSWIN) {
            if (($SemId = sem_get( SSM_ID_KEY, 0664, false )) == false)
                user_error( 'Couldn\'t open semaphore', E_USER_ERROR );
            sem_acquire( $SemId );
        }
        $data = $this->get_shmapp();
        if ($this->userLockSemId == 0 && !IAD_IS_MSWIN)
            sem_release( $SemId );
        if (isset( $data[ $name ] ))
            return $data[ $name ];
        else
            return NULL;
    }
   
    public function Lock() {
        if (!IAD_IS_MSWIN) {
            if ($this->userLockSemId != 0)
                user_error( 'Lock already opened', E_USER_ERROR );
            else {
                if (($this->userLockSemId = sem_get( SSM_ID_KEY, 0664, false )) == false)
                    user_error( 'Couldn\'t open semaphore', E_USER_ERROR );
                sem_acquire( $this->userLockSemId );
            }
        }
    }
   
    public function Unlock() {
        if (!IAD_IS_MSWIN) {
            if ($this->userLockSemId == 0)
                user_error( 'Lock does not exist', E_USER_ERROR );
            else
                sem_release( $this->userLockSemId );
        }
    }
   
    // destroy memory segment
    public function Destroy() {
        if ($this->SHMDataBlockId == 0) // prima chiamata nell'arco di questo script
            if (!($this->SHMDataBlockId = shmop_open( $this->ContextID, 'w', 0, 0 )))
                return;
        shmop_write( $this->SHMDataBlockId, serialize( NULL ), 0 );
        shmop_delete( $this->SHMDataBlockId );
        shmop_close( $this->SHMDataBlockId );
        $this->SHMDataBlockId = 0;
    }

    // not fast
    public function getOpenedContexts() {
        if (!isset($GLOBALS['SAVEAPPCONTEXTS']) || !$GLOBALS['SAVEAPPCONTEXTS']) {
            user_error( "getOpenedContexts(): In this script SAVEAPPCONTEXTS is false or not set", E_USER_NOTICE );
        }
        if ($this->ContextID != IAD_APPLICATION_SERVER_SHMID) {
            $o = new ApplicationData( IAD_SERVER );
            return $o->getOpenedContexts();
        }
        else {
            $output = array();
            $ServerData = $this->get_shmapp();
            if (is_array($ServerData)) {
                foreach (array_keys($ServerData) as $k => $v) {
                    if (substr( $k, 0, 3 ) == '$$[')
                        $output[$k] = $v;
                }
            }
            return $output;
        }
    }
   
}

?>
