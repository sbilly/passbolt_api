<?php
/**
 * Insert Secret Task
 *
 * @copyright (c) 2015-present Bolt Softwares Pvt Ltd
 * @licence GNU Affero General Public License http://www.gnu.org/licenses/agpl-3.0.en.html
 * @package      app.plugins.DataExtras.Console.Command.Task.SecretTask
 * @since        version 2.12.11
 */

require_once(ROOT . DS . APP_DIR . DS . 'Console' . DS . 'Command' . DS . 'Task' . DS . 'ModelTask.php');

App::uses('Secret', 'Model');
App::uses('Resource', 'Model');
App::uses('User', 'Model');
App::uses('Gpgkey', 'Model');

class SecretTask extends ModelTask {

	public $model = 'Secret';

/**
 * Get Dummy Secret Data.
 *
 * The passwords are always returned in the same order, useful for cross checking.
 * This secret was encrypted with the dummy public key, also located in the repository.
 *
 * @return string
 */
	protected function getDummyPassword() {
		static $i = 0;
		$passwords = array(
			"testpassword",
			"123456",
			"qwerty",
			"111111",
			"iloveyou",
			"adbobe123",
			"admin",
			"letmein",
			"monkey",
			"adobe",
			"sunshine",
			"princess",
			"azerty",
			"trustno1",
			"iamgod",
			"love",
			"god",
			"business",
			"passbolt",
			"enova",
			"kevisthebest",
		);
		$i++;
		if ($i > sizeof($passwords) - 1) {
			$i = 0;
		}
		return $passwords[$i];
	}

/**
 * Encrypt a password with the user public key.
 * @param $password
 * @param $userId
 * @return string $encrypted encrypted password
 */
	protected function encryptPassword($password, $userId) {
		//$GpgkeyTask = $this->Tasks->load('Data.Gpgkey');
		//$gpgkeyPath = $GpgkeyTask->getGpgkeyPath($userId);

		$Gpgkey = $this->_getModel('Gpgkey');
		$key = $Gpgkey->find("first", array('conditions' => array(
			'Gpgkey.user_id' => $userId,
			'Gpgkey.deleted' => 0
		)));

		if (!isset($key['Gpgkey'])) {
			$this->out('Could not find GPG key for user ' . $userId);
			$this->out('<error>Installation failed</error>');
			die;
		}

		try {
			$res = gnupg_init();
			gnupg_seterrormode($res,GNUPG_ERROR_WARNING);
			gnupg_import($res, $key['Gpgkey']['key']);
			gnupg_addencryptkey($res , $key['Gpgkey']['fingerprint']);
		} catch(Exception $e) {
			$this->out('Could not import GPG key:');
			$this->out(pr($key));
			$this->out('<error>Installation failed</error>');
			die;
		}

		$encrypted = gnupg_encrypt($res , $password);

		return $encrypted;
	}

/**
 * Get all Secret data.
 *
 * @return array
 */
	protected function getData() {
		$Resource = $this->_getModel('Resource');
		$Resource->Behaviors->disable('Permissionable'); // cannot do a findAll otherwise
		$User = $this->_getModel('User');
		$rs = $Resource->find('all');
		$us = $User->find('all');

		// Insertion for all users who can access to available resources.
		// We insert dummy data, same secret for everyone.
		$s = [];
		foreach($rs as $r) {
			$password = $this->getDummyPassword();
			foreach ($us as $u) {
				$isAuthorized = $Resource->isAuthorized($r['Resource']['id'], PermissionType::READ, $u['User']['id']);

				if ($isAuthorized) {
					$passwordEncrypted = $this->encryptPassword($password, $u['User']['id']);
					$s[] = array('Secret' => array(
						'id' => Common::uuid(),
						'user_id' => $u['User']['id'],
						'resource_id' => $r['Resource']['id'],
						'data' => $passwordEncrypted,
						'created' => '2012-12-24 03:34:40',
						'modified' => '2012-12-24 03:34:40',
						'created_by' => $u['User']['id'],
						'modified_by' => $u['User']['id']
					));
				}
			}
		}
		return $s;
	}
}