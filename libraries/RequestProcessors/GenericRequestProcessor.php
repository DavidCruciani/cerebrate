<?php
use Cake\ORM\TableRegistry;
use Cake\Filesystem\File;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

interface GenericProcessorActionI
{
    public function create($requestData);
    public function process($requestID, $serverRequest);
    public function discard($requestID ,$requestData);
    public function setViewVariables($controller, $request);
}

class GenericRequestProcessor
{
    protected $Inbox;
    protected $registeredActions = [];
    protected $validator;
    private $processingTemplate = '/genericTemplates/confirm';
    private $processingTemplatesDirectory = ROOT . '/templates/RequestProcessors';

    public function __construct($registerActions=false) {
        $this->Inbox = TableRegistry::getTableLocator()->get('Inbox');
        if ($registerActions) {
            $this->registerActionInProcessor();
        }
        $processingTemplatePath = $this->getProcessingTemplatePath();
        $file = new File($this->processingTemplatesDirectory . DS . $processingTemplatePath);
        if ($file->exists()) {
            $this->processingTemplate = $processingTemplatePath;
        }
        $file->close();
    }

    private function getProcessingTemplatePath()
    {
        $class = str_replace('RequestProcessor', '', get_parent_class($this));
        $action = strtolower(str_replace('Processor', '', get_class($this)));
        return sprintf('%s/%s.php',
            $class,
            $action
        );
    }

    public function getProcessingTemplate()
    {
        if ($this->processingTemplate == '/genericTemplates/confirm') {
            return '/genericTemplates/confirm';
        }
        return DS . 'RequestProcessors' . DS . str_replace('.php', '', $this->processingTemplate);
    }

    protected function generateRequest($requestData)
    {
        $request = $this->Inbox->newEmptyEntity();
        $request = $this->Inbox->patchEntity($request, $requestData);
        if ($request->getErrors()) {
            throw new Exception(__('Could not create request.{0}Reason: {1}', PHP_EOL, json_encode($request->getErrors())), 1);
        }
        return $request;
    }

    protected function validateRequestData($requestData)
    {
        $errors = [];
        if (!isset($requestData['data'])) {
            $errors[] = __('No request data provided');
        }
        $validator = new Validator();
        if (method_exists($this, 'addValidatorRules')) {
            $validator = $this->addValidatorRules($validator);
            $errors = $validator->validate($requestData['data']);
        }
        if (!empty($errors)) {
            throw new Exception('Error while validating request data. ' . json_encode($errors), 1);
        }
    }

    protected function registerActionInProcessor()
    {
        foreach ($this->registeredActions as $i => $action) {
            $className = "{$action}Processor";
            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                throw new Exception(__('Cannot create instance of %s, as it is abstract or is an interface'));
            }
            $this->{$action} = $reflection->newInstance();
        }
    }
    
    protected function setViewVariablesConfirmModal($controller, $id, $title='', $question='', $actionName='')
    {
        $controller->set('title', !empty($title) ? $title : __('Process request {0}', $id));
        $controller->set('question', !empty($question) ? $question : __('Confirm request {0}', $id));
        $controller->set('actionName', !empty($actionName) ? $actionName : __('Confirm'));
        $controller->set('path', ['controller' => 'inbox', 'action' => 'process', $id]);
    }

    protected function genActionResult($data, $success, $message, $errors=[])
    {
        return [
            'data' => $data,
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    public function genHTTPReply($controller, $processResult, $request, $redirect=null)
    {
        if (is_array($request)) {
            $scope = $request['scope'];
            $action = $request['action'];
        } else {
            $scope = $request->scope;
            $action = $request->action;
        }
        if ($processResult['success']) {
            $message = !empty($processResult['message']) ? $processResult['message'] : __('Request {0} successfully processed.', $id);
            if ($controller->ParamHandler->isRest()) {
                $response = $controller->RestResponse->viewData($processResult, 'json');
            } else if ($controller->ParamHandler->isAjax()) {
                $response = $controller->RestResponse->ajaxSuccessResponse('RequestProcessor', "{$scope}.{$action}", $processResult['data'], $message);
            } else {
                $controller->Flash->success($message);
                if (!is_null($redirect)) {
                    $response = $controller->redirect($redirect);
                } else {
                    $response = $controller->redirect(['action' => 'index']);
                }
            }
        } else {
            $message = !empty($processResult['message']) ? $processResult['message'] : __('Request {0} could not be processed.', $id);
            if ($controller->ParamHandler->isRest()) {
                $response = $controller->RestResponse->viewData($processResult, 'json');
            } else if ($controller->ParamHandler->isAjax()) {
                $response = $controller->RestResponse->ajaxFailResponse('RequestProcessor', "{$scope}.{$action}", $processResult['data'], $message, $processResult['errors']);
            } else {
                $controller->Flash->error($message);
                if (!is_null($redirect)) {
                    $response = $controller->redirect($redirect);
                } else {
                    $response = $controller->redirect(['action' => 'index']);
                }
            }
        }

        return $response;
    }

    public function checkLoading()
    {
        return 'Assimilation successful!';
    }
    
    public function create($requestData)
    {
        $requestData['scope'] = $this->scope;
        $requestData['action'] = $this->action;
        $requestData['description'] = $this->description;
        $request = $this->generateRequest($requestData);
        $savedRequest = $this->Inbox->save($request);
        return $this->genActionResult(
            $savedRequest,
            $savedRequest !== false,
            __('{0} request for {1} created', $this->scope, $this->action),
            $request->getErrors()
        );
    }

    public function discard($id, $requestData)
    {
        $request = $this->Inbox->get($id);
        $this->Inbox->delete($request);
        return $this->genActionResult(
            [],
            true,
            __('{0}.{1} request #{2} discarded', $this->scope, $this->action, $id)
        );
    }
}
