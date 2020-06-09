<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Event\EventInterface;
use Cake\Utility\Text;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Error\Debugger;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{

    public $isRest = null;
    public $restResponsePayload = null;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        $this->loadComponent('RestResponse');
        $this->loadComponent('ACL');
        $this->loadComponent('ParamHandler', [
            'request' => $this->request
        ]);
        $this->loadComponent('CRUD', [
            'request' => $this->request,
            'table' => $this->{$this->modelClass}
        ]);

        if (Configure::read('debug')) {
            Configure::write('DebugKit.panels', ['DebugKit.Packages' => true]);
            Configure::write('DebugKit.forceEnable', true);
        }

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeFilter(EventInterface $event)
    {
        $this->isAdmin = true;
        $this->set('menu', $this->{$this->modelClass}->getMenu());
        $this->set('ajax', $this->request->is('ajax'));
    }

    public function generateUUID()
    {
        $uuid = Text::uuid();
        return $this->RestResponse->viewData(['uuid' => $uuid], 'json');
    }

    public function checkPermission($perm_flag)
    {
        return true;
    }
}