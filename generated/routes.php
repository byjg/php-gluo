<?php

// The compiled routes (ByJG\RestServer\Route\CompiledRouteList::compile()). Do not edit.

return array (
  'routes' => 
  array (
    'POST /login/confirmcode' => 
    array (
      0 => 'POST',
      1 => '/login/confirmcode',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\LoginController',
        1 => 'postConfirmCode',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/login/confirmcode',
      ),
    ),
    'POST /login/resetpassword' => 
    array (
      0 => 'POST',
      1 => '/login/resetpassword',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\LoginController',
        1 => 'postResetPassword',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/login/resetpassword',
      ),
    ),
    'POST /login/resetrequest' => 
    array (
      0 => 'POST',
      1 => '/login/resetrequest',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\LoginController',
        1 => 'postResetRequest',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/login/resetrequest',
      ),
    ),
    'POST /login' => 
    array (
      0 => 'POST',
      1 => '/login',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\LoginController',
        1 => 'post',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/login',
      ),
    ),
    'GET /note' => 
    array (
      0 => 'GET',
      1 => '/note',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'listNote',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/note',
      ),
    ),
    'POST /note' => 
    array (
      0 => 'POST',
      1 => '/note',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'postNote',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/note',
      ),
    ),
    'GET /profile' => 
    array (
      0 => 'GET',
      1 => '/profile',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProfileController',
        1 => 'getProfile',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/profile',
      ),
    ),
    'PUT /profile' => 
    array (
      0 => 'PUT',
      1 => '/profile',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProfileController',
        1 => 'putProfile',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/profile',
      ),
    ),
    'GET /project' => 
    array (
      0 => 'GET',
      1 => '/project',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProjectController',
        1 => 'listProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project',
      ),
    ),
    'POST /project' => 
    array (
      0 => 'POST',
      1 => '/project',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProjectController',
        1 => 'postProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project',
      ),
    ),
    'POST /refreshtoken' => 
    array (
      0 => 'POST',
      1 => '/refreshtoken',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\LoginController',
        1 => 'refreshToken',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/refreshtoken',
      ),
    ),
    'GET /sample/ping' => 
    array (
      0 => 'GET',
      1 => '/sample/ping',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\SampleController',
        1 => 'getPing',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/sample/ping',
      ),
    ),
    'GET /sampleprotected/pingadm' => 
    array (
      0 => 'GET',
      1 => '/sampleprotected/pingadm',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\SampleProtectedController',
        1 => 'getPingAdm',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/sampleprotected/pingadm',
      ),
    ),
    'GET /sampleprotected/ping' => 
    array (
      0 => 'GET',
      1 => '/sampleprotected/ping',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\SampleProtectedController',
        1 => 'getPing',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/sampleprotected/ping',
      ),
    ),
    'GET /task' => 
    array (
      0 => 'GET',
      1 => '/task',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\TaskController',
        1 => 'listTask',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/task',
      ),
    ),
    'POST /task' => 
    array (
      0 => 'POST',
      1 => '/task',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\TaskController',
        1 => 'postTask',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/task',
      ),
    ),
    'GET /note/{id}' => 
    array (
      0 => 'GET',
      1 => '/note/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'getNote',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/note/{id}',
      ),
    ),
    'PUT /note/{id}' => 
    array (
      0 => 'PUT',
      1 => '/note/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonCleanOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'putNote',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/note/{id}',
      ),
    ),
    'DELETE /note/{id}' => 
    array (
      0 => 'DELETE',
      1 => '/note/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonCleanOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'deleteNote',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/note/{id}',
      ),
    ),
    'GET /project/{id}' => 
    array (
      0 => 'GET',
      1 => '/project/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProjectController',
        1 => 'getProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project/{id}',
      ),
    ),
    'PUT /project/{id}' => 
    array (
      0 => 'PUT',
      1 => '/project/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonCleanOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\ProjectController',
        1 => 'putProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project/{id}',
      ),
    ),
    'GET /project/{projectid}/note' => 
    array (
      0 => 'GET',
      1 => '/project/{projectId}/note',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\NoteController',
        1 => 'listNotesByProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project/{projectId}/note',
      ),
    ),
    'GET /project/{projectid}/task' => 
    array (
      0 => 'GET',
      1 => '/project/{projectId}/task',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\TaskController',
        1 => 'listTasksByProject',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/project/{projectId}/task',
      ),
    ),
    'GET /task/{id}' => 
    array (
      0 => 'GET',
      1 => '/task/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\TaskController',
        1 => 'getTask',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/task/{id}',
      ),
    ),
    'PUT /task/{id}' => 
    array (
      0 => 'PUT',
      1 => '/task/{id}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonCleanOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'RestReferenceArchitecture\\Controller\\TaskController',
        1 => 'putTask',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/task/{id}',
      ),
    ),
  ),
  'dispatch' => 
  array (
    0 => 
    array (
      'POST' => 
      array (
        '/login/confirmcode' => 'POST /login/confirmcode',
        '/login/resetpassword' => 'POST /login/resetpassword',
        '/login/resetrequest' => 'POST /login/resetrequest',
        '/login' => 'POST /login',
        '/note' => 'POST /note',
        '/project' => 'POST /project',
        '/refreshtoken' => 'POST /refreshtoken',
        '/task' => 'POST /task',
      ),
      'GET' => 
      array (
        '/note' => 'GET /note',
        '/profile' => 'GET /profile',
        '/project' => 'GET /project',
        '/sample/ping' => 'GET /sample/ping',
        '/sampleprotected/pingadm' => 'GET /sampleprotected/pingadm',
        '/sampleprotected/ping' => 'GET /sampleprotected/ping',
        '/task' => 'GET /task',
      ),
      'PUT' => 
      array (
        '/profile' => 'PUT /profile',
      ),
    ),
    1 => 
    array (
      'GET' => 
      array (
        0 => 
        array (
          'regex' => '~^(?|/note/([^/]+)|/project/([^/]+)()|/project/([^/]+)/note()()|/project/([^/]+)/task()()()|/task/([^/]+)()()()())$~',
          'routeMap' => 
          array (
            2 => 
            array (
              0 => 'GET /note/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
            3 => 
            array (
              0 => 'GET /project/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
            4 => 
            array (
              0 => 'GET /project/{projectid}/note',
              1 => 
              array (
                'projectId' => 'projectId',
              ),
            ),
            5 => 
            array (
              0 => 'GET /project/{projectid}/task',
              1 => 
              array (
                'projectId' => 'projectId',
              ),
            ),
            6 => 
            array (
              0 => 'GET /task/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
          ),
        ),
      ),
      'PUT' => 
      array (
        0 => 
        array (
          'regex' => '~^(?|/note/([^/]+)|/project/([^/]+)()|/task/([^/]+)()())$~',
          'routeMap' => 
          array (
            2 => 
            array (
              0 => 'PUT /note/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
            3 => 
            array (
              0 => 'PUT /project/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
            4 => 
            array (
              0 => 'PUT /task/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
          ),
        ),
      ),
      'DELETE' => 
      array (
        0 => 
        array (
          'regex' => '~^(?|/note/([^/]+))$~',
          'routeMap' => 
          array (
            2 => 
            array (
              0 => 'DELETE /note/{id}',
              1 => 
              array (
                'id' => 'id',
              ),
            ),
          ),
        ),
      ),
    ),
  ),
);
