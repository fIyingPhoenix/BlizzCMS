<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model {

    /**
     * Auth_model constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->auth = $this->load->database('auth', TRUE);

        if ($this->isLogged() && $this->checkAccountExist() == 0)
        {
            $this->synchronizeAccount();
        }
    }

    public function arraySession($id)
    {
        $data = array(
            'wow_sess_username'  => $this->getUsernameID($id),
            'blizz_sess_username' => $this->getSiteUsernameID($id),
            'wow_sess_email'     => $this->getEmailID($id),
            'wow_sess_id'        => $id,
            'wow_sess_expansion'	=> $this->getExpansionID($id),
            'wow_sess_last_ip'   => $this->getLastIPID($id),
            'wow_sess_last_login'=> $this->getLastLoginID($id),
            'wow_sess_gmlevel'   => $this->getRank($id),
            'wow_sess_ban_status'=> $this->getBanStatus($id),
            'logged_in' => TRUE
        );

        return $this->sessionConnect($data);
    }

    public function getGmSpecify($id)
    {
        return $this->auth->select('id')->where('id', $id)->get('account_access');
    }

    public function randomUTF()
    {
        return rand(0, 999999999);
    }

    public function getUsernameID($id)
    {
        return $this->auth->select('username')->where('id', $id)->get('account')->row('username');
    }

    public function getSiteUsernameID($id)
    {
        return $this->db->select('username')->where('id', $id)->get('users')->row('username');
    }

    public function getEmailID($id)
    {
        return $this->auth->select('email')->where('id', $id)->get('account')->row('email');
    }

    public function getPasswordAccountID($id)
    {
        return $this->auth->select('sha_pass_hash')->where('id', $id)->get('account')->row('sha_pass_hash');
    }

    public function getPasswordBnetID($id)
    {
        return $this->auth->select('sha_pass_hash')->where('id', $id)->get('battlenet_accounts')->row('sha_pass_hash');
    }

    public function getSpecifyAccount($account)
    {
        $account = strtoupper($account);

        return $this->auth->select('id')->where('username', $account)->get('account');
    }

    public function getSpecifyEmail($email)
    {
        return $this->auth->select('id')->where('email', $email)->get('account');
    }

    public function getIDAccount($account)
    {
        $account = strtoupper($account);

        $qq = $this->auth->select('id')->where('username', $account)->get('account');
        
        if($qq->num_rows())
            return $qq->row('id');
        else
            return '0';
    }

    public function getImageProfile($id)
    {
        return $this->db->select('profile')->where('id', $id)->get('users')->row('profile');
    }

    public function getNameAvatar($id)
    {
        return $this->db->select('name')->where('id', $id)->get('avatars')->row('name');
    }

    public function getIDEmail($email)
    {
        $email = strtoupper($email);

        $qq = $this->auth->select('id')->where('email', $email)->get('account');

        if($qq->num_rows())
            return $qq->row('id');
        else
            return '0';
    }

    public function getExpansionID($id)
    {
        return $this->auth->select('expansion')->where('id', $id)->get('account')->row('expansion');
    }

    public function getLastIPID($id)
    {
        return $this->auth->select('last_ip')->where('id', $id)->get('account')->row('last_ip');
    }

    public function getLastLoginID($id)
    {
        return $this->auth->select('last_login')->where('id', $id)->get('account')->row('last_login');
    }

    public function getJoinDateID($id)
    {
        return $this->auth->select('joindate')->where('id', $id)->get('account')->row('joindate');
    }

    public function getRank($id)
    {
        $qq = $this->auth->select('gmlevel')->where('id', $id)->get('account_access');

        if($qq->num_rows())
            return $qq->row('gmlevel');
        else
            return '0';
    }

    public function getBanStatus($id)
    {
        $qq = $this->auth->select('*')->where('id', $id)->where('active', '1')->get('account_banned');

        if ($qq->num_rows())
            return true;
        else
            return false;
    }

    public function isLogged()
    {
        if ($this->session->userdata('wow_sess_username'))
            return true;
        else
            return false;
    }

    public function sessionConnect($data)
    {
        $this->session->set_userdata($data);
        return true;
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect(base_url(),'refresh');
    }

    /*
    public function Battlenet($email, $password)
    {
        return strtoupper(bin2hex(strrev(hex2bin(strtoupper(hash("sha256",strtoupper(hash("sha256", strtoupper($email)).":".strtoupper($password))))))));
    }

    public function Account($username, $password)
    {
        if (!is_string($username))
            $username = "";

        if (!is_string($password))
            $password = "";

        $sha_pass_hash = sha1(strtoupper($username).':'.strtoupper($password));

        return strtoupper($sha_pass_hash);
    }
    */

    public function game_hash($username, $password, $type = null, $salt = null)
    {
        switch ($type)
		{
			case 'bnet':
				return strtoupper(bin2hex(strrev(hex2bin(strtoupper(hash('sha256', strtoupper(hash('sha256', strtoupper($username)) . ':' . strtoupper($password))))))));
				break;
			case 'hex':
				$client = new UserClient($username, $salt);
				return strtoupper($client->generateVerifier($password));
			case 'srp6':
				// Constants
				$g = gmp_init(7);
				$N = gmp_init('894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7', 16);
				// Calculate first hash
				$h1 = sha1(strtoupper($username.':'.$password), TRUE);
				// Calculate second hash
				$h2 = sha1($salt.$h1, TRUE);
				// Convert to integer (little-endian)
				$h2 = gmp_import($h2, 1, GMP_LSW_FIRST);
				// g^h2 mod N
				$verifier = gmp_powm($g, $h2, $N);
				// Convert back to a byte array (little-endian)
				$verifier = gmp_export($verifier, 1, GMP_LSW_FIRST);
				// Pad to 32 bytes, remember that zeros go on the end in little-endian!
				$verifier = str_pad($verifier, 32, chr(0), STR_PAD_RIGHT);
				return $verifier;
				break;
			default:
				return strtoupper(sha1(strtoupper($username) . ':' . strtoupper($password)));
				break;
		}

    }

    /**
	 * Check if username and email is unique in auth
	 *
	 * @param string $data
	 * @param string $column
	 * @return bool
	 */
	public function account_unique($data, $column = 'username')
	{
		$query = $this->auth->where($column, $data)->get('account')->num_rows();

		return ($query == 0);
	}

    public function checkAccountExist()
    {
        return $this->db->select('id')->where('id', $this->session->userdata('wow_sess_id'))->get('users')->num_rows();
    }

    public function synchronizeAccount()
    {
        if ($this->checkAccountExist() == 0)
        {
            $joindate = strtotime($this->getJoinDateID($this->session->userdata('wow_sess_id')));

            $data = array(
                'id' => $this->session->userdata('wow_sess_id'),
                'username' => $this->session->userdata('wow_sess_username'),
                'email' => $this->session->userdata('wow_sess_email'),
                'joindate' => $joindate
            );

            $this->db->insert('users', $data);
            return true;
        }
        else
            return false;
    }

    public function getRankByLevel($gmlevel)
    {
        $qq = $this->auth->select('gmlevel')->where('id', $this->session->userdata('wow_sess_id'))->get('account_access');

        $gmlevel = $this->db->select('comment')->where('permission', $qq->row('gmlevel'))->get('ranks_default');

        if($gmlevel->num_rows())
            return $gmlevel->row('comment');
        else
        {
            return 'Player';
        }
    }

    public function getIsAdmin($id)
    {
        $config = $this->config->item('admin_access_level');

        $qq = $this->auth->select('gmlevel')->where('id', $this->session->userdata('wow_sess_id'))->get('account_access');

        if(!$qq->row('gmlevel'))
            return false;
        else
        {
            if($qq->row('gmlevel') >= $config)
                return true;
            else
            {
                return false;
            }
        }
    }

    public function getIsModerator($id)
    {
        $config = $this->config->item('mod_access_level');

        $qq = $this->auth->select('gmlevel')->where('id', $this->session->userdata('wow_sess_id'))->get('account_access');

        if(!$qq->row('gmlevel'))
            return false;
        else
        {
            if($qq->row('gmlevel') >= $config)
                return true;
            else
            {
                return false;
            }
        }
    }

    public function getMaintenancePermission()
    {
        $config = $this->config->item('mod_access_level');

        $qq = $this->auth->select('gmlevel')->where('id', $this->session->userdata('wow_sess_id'))->get('account_access');

        if(!$qq->row('gmlevel'))
            return false;
        else
        {
            if($qq->row('gmlevel') >= $config)
                return false;
            else
            {
                return true;
            }
        }
    }
}
