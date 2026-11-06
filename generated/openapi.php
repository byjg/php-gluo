<?php

// public/docs/openapi.json as a PHP array (php gluo openapi). Do not edit.

return array (
  'openapi' => '3.1.0',
  'info' => 
  array (
    'title' => 'Gluo API',
    'description' => 'REST API built on Gluo',
    'termsOfService' => 'http://localhost:8080/terms/',
    'contact' => 
    array (
      'email' => 'someone@example.com',
    ),
    'license' => 
    array (
      'name' => 'Proprietary',
    ),
    'version' => '1.0.0',
  ),
  'paths' => 
  array (
    '/login' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Login',
        ),
        'summary' => 'Do login',
        'operationId' => 'POST::/login::RestReferenceArchitecture\\Controller\\LoginController::post',
        'requestBody' => 
        array (
          'description' => 'The Login Data',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'username',
                  1 => 'password',
                ),
                'properties' => 
                array (
                  'username' => 
                  array (
                    'description' => 'The Username',
                    'type' => 'string',
                    'format' => 'string',
                  ),
                  'password' => 
                  array (
                    'description' => 'The Password',
                    'type' => 'string',
                    'format' => 'string',
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object to be created',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'token',
                  ),
                  'properties' => 
                  array (
                    'token' => 
                    array (
                      'type' => 'string',
                    ),
                    'data' => 
                    array (
                      'properties' => 
                      array (
                        'userid' => 
                        array (
                          'type' => 'string',
                        ),
                        'name' => 
                        array (
                          'type' => 'string',
                        ),
                        'role' => 
                        array (
                          'type' => 'string',
                        ),
                      ),
                      'type' => 'object',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/refreshtoken' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Login',
        ),
        'summary' => 'Refresh Token',
        'operationId' => 'POST::/refreshtoken::RestReferenceArchitecture\\Controller\\LoginController::refreshToken',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The refreshed token',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'token',
                  ),
                  'properties' => 
                  array (
                    'token' => 
                    array (
                      'type' => 'string',
                    ),
                    'data' => 
                    array (
                      'properties' => 
                      array (
                        'role' => 
                        array (
                          'type' => 'string',
                        ),
                        'userid' => 
                        array (
                          'type' => 'string',
                        ),
                        'name' => 
                        array (
                          'type' => 'string',
                        ),
                      ),
                      'type' => 'object',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Unauthorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/login/resetrequest' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Login',
        ),
        'summary' => 'Request a password reset code by email',
        'operationId' => 'POST::/login/resetrequest::RestReferenceArchitecture\\Controller\\LoginController::postResetRequest',
        'requestBody' => 
        array (
          'description' => 'The email to have the password reset',
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'email',
                ),
                'properties' => 
                array (
                  'email' => 
                  array (
                    'type' => 'string',
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The token for reset',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'token',
                  ),
                  'properties' => 
                  array (
                    'token' => 
                    array (
                      'type' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/login/confirmcode' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Login',
        ),
        'summary' => 'Confirm the password reset code',
        'operationId' => 'POST::/login/confirmcode::RestReferenceArchitecture\\Controller\\LoginController::postConfirmCode',
        'requestBody' => 
        array (
          'description' => 'The email and code to confirm the password reset',
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'email',
                  1 => 'token',
                  2 => 'code',
                ),
                'properties' => 
                array (
                  'email' => 
                  array (
                    'type' => 'string',
                  ),
                  'token' => 
                  array (
                    'type' => 'string',
                  ),
                  'code' => 
                  array (
                    'type' => 'string',
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The token for reset',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'token',
                  ),
                  'properties' => 
                  array (
                    'token' => 
                    array (
                      'type' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          422 => 
          array (
            'description' => 'Invalid data',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/login/resetpassword' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Login',
        ),
        'summary' => 'Set a new password with the confirmed reset token',
        'operationId' => 'POST::/login/resetpassword::RestReferenceArchitecture\\Controller\\LoginController::postResetPassword',
        'requestBody' => 
        array (
          'description' => 'The email and the new password',
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'email',
                  1 => 'token',
                  2 => 'password',
                ),
                'properties' => 
                array (
                  'email' => 
                  array (
                    'type' => 'string',
                  ),
                  'token' => 
                  array (
                    'type' => 'string',
                  ),
                  'password' => 
                  array (
                    'type' => 'string',
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The token for reset',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'token',
                  ),
                  'properties' => 
                  array (
                    'token' => 
                    array (
                      'type' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          422 => 
          array (
            'description' => 'Invalid data',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/note/{id}' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'Get the Note by id',
        'operationId' => 'GET::/note/{id}::RestReferenceArchitecture\\Controller\\NoteController::getNote',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Note',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/Note',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'put' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'Update an existing Note',
        'operationId' => 'PUT::/note/{id}::RestReferenceArchitecture\\Controller\\NoteController::putNote',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'requestBody' => 
        array (
          'description' => 'The object Note to be updated',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'taskId',
                  1 => 'body',
                ),
                'properties' => 
                array (
                  'taskId' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                  ),
                  'body' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 500,
                    'minLength' => 1,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'Nothing to return',
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'delete' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'Soft-delete a Note',
        'description' => 'Because the model uses the OaDeletedAt trait, delete()
sets deleted_at instead of removing the row, and the record then disappears
from get()/all() while remaining in the table.',
        'operationId' => 'DELETE::/note/{id}::RestReferenceArchitecture\\Controller\\NoteController::deleteNote',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'Nothing to return',
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/note' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'List Note',
        'operationId' => 'GET::/note::RestReferenceArchitecture\\Controller\\NoteController::listNote',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'page',
            'in' => 'query',
            'description' => 'Page number',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          1 => 
          array (
            'name' => 'size',
            'in' => 'query',
            'description' => 'Page size',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          2 => 
          array (
            'name' => 'orderBy',
            'in' => 'query',
            'description' => 'Fields to sort by, separated by commas; add \' desc\' after a field for descending order. Example: name desc,id',
            'required' => false,
            'schema' => 
            array (
              'type' => 'string',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Note',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'type' => 'array',
                  'items' => 
                  array (
                    '$ref' => '#/components/schemas/Note',
                  ),
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'Create a new Note',
        'operationId' => 'POST::/note::RestReferenceArchitecture\\Controller\\NoteController::postNote',
        'requestBody' => 
        array (
          'description' => 'The object Note to be created',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'taskId',
                  1 => 'body',
                ),
                'properties' => 
                array (
                  'taskId' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                  ),
                  'body' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 500,
                    'minLength' => 1,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object to be created',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'id',
                  ),
                  'properties' => 
                  array (
                    'id' => 
                    array (
                      'type' => 'integer',
                      'format' => 'int32',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/project/{projectId}/note' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Note',
        ),
        'summary' => 'List every note across a whole project',
        'description' => 'Spans note -> task -> project. A note only
carries a task_id, so this spans two relationships; Note::getByProjectId() uses
joinWith(Task::class, Project::class), naming the through-entity so its mapper
registers on demand and both JOINs resolve.',
        'operationId' => 'GET::/project/{projectId}/note::RestReferenceArchitecture\\Controller\\NoteController::listNotesByProject',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'projectId',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The notes belonging to the project',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'type' => 'array',
                  'items' => 
                  array (
                    '$ref' => '#/components/schemas/Note',
                  ),
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/profile' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Profile',
        ),
        'summary' => 'Return the profile of the logged-in user (from the JWT).',
        'operationId' => 'GET::/profile::RestReferenceArchitecture\\Controller\\ProfileController::getProfile',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The profile of the authenticated user',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'userid',
                    1 => 'name',
                    2 => 'email',
                  ),
                  'properties' => 
                  array (
                    'userid' => 
                    array (
                      'type' => 'string',
                    ),
                    'name' => 
                    array (
                      'type' => 'string',
                    ),
                    'email' => 
                    array (
                      'type' => 'string',
                    ),
                    'username' => 
                    array (
                      'type' => 'string',
                    ),
                    'language' => 
                    array (
                      'type' => 
                      array (
                        0 => 'string',
                        1 => 'null',
                      ),
                      'enum' => 
                      array (
                        0 => 'en',
                        1 => 'fr',
                        2 => 'pt',
                      ),
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'put' => 
      array (
        'tags' => 
        array (
          0 => 'Profile',
        ),
        'summary' => 'Update the name/email of the logged-in user.',
        'operationId' => 'PUT::/profile::RestReferenceArchitecture\\Controller\\ProfileController::putProfile',
        'requestBody' => 
        array (
          'description' => 'The profile fields to update',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'name',
                  1 => 'email',
                ),
                'properties' => 
                array (
                  'name' => 
                  array (
                    'type' => 'string',
                  ),
                  'email' => 
                  array (
                    'type' => 'string',
                  ),
                  'language' => 
                  array (
                    'type' => 
                    array (
                      0 => 'string',
                      1 => 'null',
                    ),
                    'enum' => 
                    array (
                      0 => 'en',
                      1 => 'fr',
                      2 => 'pt',
                    ),
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'Profile updated',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'userid',
                    1 => 'name',
                    2 => 'email',
                  ),
                  'properties' => 
                  array (
                    'userid' => 
                    array (
                      'type' => 'string',
                    ),
                    'name' => 
                    array (
                      'type' => 'string',
                    ),
                    'email' => 
                    array (
                      'type' => 'string',
                    ),
                    'language' => 
                    array (
                      'type' => 
                      array (
                        0 => 'string',
                        1 => 'null',
                      ),
                      'enum' => 
                      array (
                        0 => 'en',
                        1 => 'fr',
                        2 => 'pt',
                      ),
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/project/{id}' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Project',
        ),
        'summary' => 'Get the Project by id',
        'operationId' => 'GET::/project/{id}::RestReferenceArchitecture\\Controller\\ProjectController::getProject',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Project',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/Project',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'put' => 
      array (
        'tags' => 
        array (
          0 => 'Project',
        ),
        'summary' => 'Update an existing Project',
        'operationId' => 'PUT::/project/{id}::RestReferenceArchitecture\\Controller\\ProjectController::putProject',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'requestBody' => 
        array (
          'description' => 'The object Project to be updated',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'name',
                ),
                'properties' => 
                array (
                  'name' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 100,
                    'minLength' => 1,
                  ),
                  'description' => 
                  array (
                    'type' => 
                    array (
                      0 => 'string',
                      1 => 'null',
                    ),
                    'format' => 'string',
                    'maxLength' => 255,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'Nothing to return',
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/project' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Project',
        ),
        'summary' => 'List Project',
        'operationId' => 'GET::/project::RestReferenceArchitecture\\Controller\\ProjectController::listProject',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'page',
            'in' => 'query',
            'description' => 'Page number',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          1 => 
          array (
            'name' => 'size',
            'in' => 'query',
            'description' => 'Page size',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          2 => 
          array (
            'name' => 'orderBy',
            'in' => 'query',
            'description' => 'Fields to sort by, separated by commas; add \' desc\' after a field for descending order. Example: name desc,id',
            'required' => false,
            'schema' => 
            array (
              'type' => 'string',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Project',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'type' => 'array',
                  'items' => 
                  array (
                    '$ref' => '#/components/schemas/Project',
                  ),
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Project',
        ),
        'summary' => 'Create a new Project',
        'operationId' => 'POST::/project::RestReferenceArchitecture\\Controller\\ProjectController::postProject',
        'requestBody' => 
        array (
          'description' => 'The object Project to be created',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'name',
                ),
                'properties' => 
                array (
                  'name' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 100,
                    'minLength' => 1,
                  ),
                  'description' => 
                  array (
                    'type' => 
                    array (
                      0 => 'string',
                      1 => 'null',
                    ),
                    'format' => 'string',
                    'maxLength' => 255,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The created object',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'id',
                  ),
                  'properties' => 
                  array (
                    'id' => 
                    array (
                      'type' => 'integer',
                      'format' => 'int32',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/sample/ping' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Sample',
        ),
        'summary' => 'Simple ping',
        'operationId' => 'GET::/sample/ping::RestReferenceArchitecture\\Controller\\SampleController::getPing',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'result',
                  ),
                  'properties' => 
                  array (
                    'result' => 
                    array (
                      'type' => 'string',
                      'format' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/sampleprotected/ping' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Sample',
        ),
        'summary' => 'Sample Ping Only Authenticated',
        'operationId' => 'GET::/sampleprotected/ping::RestReferenceArchitecture\\Controller\\SampleProtectedController::getPing',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'result',
                  ),
                  'properties' => 
                  array (
                    'result' => 
                    array (
                      'type' => 'string',
                      'format' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Unauthorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/sampleprotected/pingadm' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Sample',
        ),
        'summary' => 'Sample Ping Only Admin',
        'operationId' => 'GET::/sampleprotected/pingadm::RestReferenceArchitecture\\Controller\\SampleProtectedController::getPingAdm',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'result',
                  ),
                  'properties' => 
                  array (
                    'result' => 
                    array (
                      'type' => 'string',
                      'format' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Unauthorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/task/{id}' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Task',
        ),
        'summary' => 'Get the Task by id',
        'operationId' => 'GET::/task/{id}::RestReferenceArchitecture\\Controller\\TaskController::getTask',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'string',
              'format' => 'string',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Task',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/Task',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'put' => 
      array (
        'tags' => 
        array (
          0 => 'Task',
        ),
        'summary' => 'Update an existing Task',
        'operationId' => 'PUT::/task/{id}::RestReferenceArchitecture\\Controller\\TaskController::putTask',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'string',
              'format' => 'string',
            ),
          ),
        ),
        'requestBody' => 
        array (
          'description' => 'The object Task to be updated',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'projectId',
                  1 => 'title',
                  2 => 'status',
                ),
                'properties' => 
                array (
                  'projectId' => 
                  array (
                    'type' => 'integer',
                    'format' => 'int32',
                  ),
                  'title' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 150,
                    'minLength' => 1,
                  ),
                  'status' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 20,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'Nothing to return',
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/task' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Task',
        ),
        'summary' => 'List Task',
        'operationId' => 'GET::/task::RestReferenceArchitecture\\Controller\\TaskController::listTask',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'page',
            'in' => 'query',
            'description' => 'Page number',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          1 => 
          array (
            'name' => 'size',
            'in' => 'query',
            'description' => 'Page size',
            'required' => false,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          2 => 
          array (
            'name' => 'orderBy',
            'in' => 'query',
            'description' => 'Fields to sort by, separated by commas; add \' desc\' after a field for descending order. Example: name desc,id',
            'required' => false,
            'schema' => 
            array (
              'type' => 'string',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The object Task',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'type' => 'array',
                  'items' => 
                  array (
                    '$ref' => '#/components/schemas/Task',
                  ),
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Task',
        ),
        'summary' => 'Create a new Task',
        'operationId' => 'POST::/task::RestReferenceArchitecture\\Controller\\TaskController::postTask',
        'requestBody' => 
        array (
          'description' => 'The object Task to be created',
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'projectId',
                  1 => 'title',
                  2 => 'status',
                ),
                'properties' => 
                array (
                  'projectId' => 
                  array (
                    'type' => 'integer',
                    'format' => 'int32',
                  ),
                  'title' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 150,
                    'minLength' => 1,
                  ),
                  'status' => 
                  array (
                    'type' => 'string',
                    'format' => 'string',
                    'maxLength' => 20,
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The created object',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'id',
                  ),
                  'properties' => 
                  array (
                    'id' => 
                    array (
                      'type' => 'string',
                      'format' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
    '/project/{projectId}/task' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Task',
        ),
        'summary' => 'List the tasks of a project, filtered by the database instead of by the client.',
        'operationId' => 'GET::/project/{projectId}/task::RestReferenceArchitecture\\Controller\\TaskController::listTasksByProject',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'projectId',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
              'format' => 'int32',
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The tasks belonging to the project',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'type' => 'array',
                  'items' => 
                  array (
                    '$ref' => '#/components/schemas/Task',
                  ),
                ),
              ),
            ),
          ),
          401 => 
          array (
            'description' => 'Not Authorized',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
        'security' => 
        array (
          0 => 
          array (
            'jwt-token' => 
            array (
            ),
          ),
        ),
      ),
    ),
  ),
  'components' => 
  array (
    'schemas' => 
    array (
      'Note' => 
      array (
        'description' => 'Class Note',
        'required' => 
        array (
          0 => 'id',
          1 => 'taskId',
          2 => 'body',
        ),
        'properties' => 
        array (
          'createdAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'updatedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'deletedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'id' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
            'readOnly' => true,
          ),
          'taskId' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'body' => 
          array (
            'type' => 'string',
            'format' => 'string',
            'maxLength' => 500,
            'minLength' => 1,
          ),
          'bodyLength' => 
          array (
            'description' => 'Read-only DB VIRTUAL GENERATED column: the database computes char_length(body)
on every read. Mapped with syncWithDb:false so the app never tries to write it.
This is the canonical "computed in the database" example.',
            'type' => 
            array (
              0 => 'integer',
              1 => 'null',
            ),
            'format' => 'int32',
            'readOnly' => true,
          ),
          'days' => 
          array (
            'description' => 'Read-only computed field: whole days elapsed since the note was created.
Unlike bodyLength, this canNOT be a DB generated column (it depends on NOW(),
which MySQL rejects in a generated expression), so getDays() derives it in PHP
from `created_at`. The FieldAttribute(syncWithDb:false) only keeps it out of
writes; the value itself is produced by the getter, not hydrated from a column.',
            'type' => 
            array (
              0 => 'integer',
              1 => 'null',
            ),
            'format' => 'int32',
            'readOnly' => true,
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'Note',
        ),
      ),
      'Project' => 
      array (
        'description' => 'Class Project',
        'required' => 
        array (
          0 => 'id',
          1 => 'name',
        ),
        'properties' => 
        array (
          'id' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
            'readOnly' => true,
          ),
          'name' => 
          array (
            'type' => 'string',
            'format' => 'string',
            'maxLength' => 100,
            'minLength' => 1,
          ),
          'description' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'string',
            'maxLength' => 255,
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'Project',
        ),
      ),
      'Task' => 
      array (
        'description' => 'Class Task',
        'required' => 
        array (
          0 => 'id',
          1 => 'projectId',
          2 => 'title',
          3 => 'status',
        ),
        'properties' => 
        array (
          'id' => 
          array (
            'type' => 'string',
            'format' => 'string',
            'readOnly' => true,
          ),
          'projectId' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'title' => 
          array (
            'type' => 'string',
            'format' => 'string',
            'maxLength' => 150,
            'minLength' => 1,
          ),
          'status' => 
          array (
            'type' => 'string',
            'format' => 'string',
            'maxLength' => 20,
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'Task',
        ),
      ),
      'User' => 
      array (
        'required' => 
        array (
          0 => 'email',
        ),
        'properties' => 
        array (
          'createdAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'updatedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'deletedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'userid' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'name' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'email' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'username' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'password' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
          'role' => 
          array (
            'type' => 'string',
            'format' => 'string',
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'User',
        ),
      ),
      'error' => 
      array (
        'properties' => 
        array (
          'error' => 
          array (
            'properties' => 
            array (
              'type' => 
              array (
                'description' => 'The exception class',
                'type' => 'string',
              ),
              'message' => 
              array (
                'description' => 'The error message',
                'type' => 'string',
              ),
              'file' => 
              array (
                'description' => 'The file that raised the error',
                'type' => 'string',
              ),
              'line' => 
              array (
                'description' => 'The line of the error',
                'type' => 'integer',
              ),
            ),
            'type' => 'object',
          ),
        ),
        'type' => 'object',
        'externalDocs' => 
        array (
          'description' => 'API reference',
          'url' => 'http://localhost:8080/docs',
        ),
      ),
    ),
    'securitySchemes' => 
    array (
      'jwt-token' => 
      array (
        'type' => 'http',
        'bearerFormat' => 'JWT',
        'scheme' => 'bearer',
      ),
    ),
  ),
  'tags' => 
  array (
    0 => 
    array (
      'name' => 'Login',
      'description' => 'Login',
    ),
    1 => 
    array (
      'name' => 'Note',
      'description' => 'Note',
    ),
    2 => 
    array (
      'name' => 'Profile',
      'description' => 'Profile',
    ),
    3 => 
    array (
      'name' => 'Project',
      'description' => 'Project',
    ),
    4 => 
    array (
      'name' => 'Sample',
      'description' => 'Sample',
    ),
    5 => 
    array (
      'name' => 'Task',
      'description' => 'Task',
    ),
  ),
);
