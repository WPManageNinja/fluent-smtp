<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Support\ValidationException;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        if ($keyStoreType == 'db') {
            if (!Arr::get($connection, 'access_key')) {
                $errors['access_key']['required'] = __('Access key is required.', 'fluent-smtp');
            }

            if (!Arr::get($connection, 'secret_key')) {
                $errors['secret_key']['required'] = __('Secret key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_AWS_ACCESS_KEY_ID') || !FLUENTMAIL_AWS_ACCESS_KEY_ID) {
                $errors['access_key']['required'] = __('Please define FLUENTMAIL_AWS_ACCESS_KEY_ID in wp-config.php file.', 'fluent-smtp');
            }

            if (!defined('FLUENTMAIL_AWS_SECRET_ACCESS_KEY') || !FLUENTMAIL_AWS_SECRET_ACCESS_KEY) {
                $errors['secret_key']['required'] = __('Please define FLUENTMAIL_AWS_SECRET_ACCESS_KEY in wp-config.php file.', 'fluent-smtp');
            }
        }

        // Validate tenant settings - configuration set is required when using a tenant
        $useTenant = Arr::get($connection, 'use_tenant') === 'yes';
        if ($useTenant) {
            if (!Arr::get($connection, 'configuration_set_name')) {
                $errors['configuration_set_name']['required'] = __('Configuration Set Name is required when using a tenant.', 'fluent-smtp');
            }
            if (!Arr::get($connection, 'tenant_name')) {
                $errors['tenant_name']['required'] = __('Tenant Name is required when tenant is enabled.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function checkConnection($connection)
    {
        $connection = $this->filterConnectionVars($connection);

        $ses = new SimpleEmailServiceV2(
            $connection['access_key'],
            $connection['secret_key'],
            $connection['region'],
            true
        );

        // Use getAccount for connection validation - simple authenticated request
        try {
            $accountInfo = $ses->getAccount();
            
            if (isset($accountInfo['error'])) {
                $this->throwValidationException(['api_error' => $accountInfo['error']]);
            }
        } catch (ValidationException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            $this->throwValidationException(['api_error' => $e->getMessage()]);
        }

        // If tenant is enabled, validate both configuration set and tenant
        $useTenant = Arr::get($connection, 'use_tenant') === 'yes';
        if ($useTenant) {
            // Validate Configuration Set exists
            if (!empty($connection['configuration_set_name'])) {
                try {
                    $configSetInfo = $ses->getConfigurationSet($connection['configuration_set_name']);
                    if (isset($configSetInfo['error'])) {
                        $errorMessage = $this->parseAwsErrorMessage($configSetInfo['error'], 'Configuration Set');
                        $this->throwValidationException(['configuration_set_name' => ['validation' => $errorMessage]]);
                    }
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    $errorMessage = $this->parseAwsErrorMessage($e->getMessage(), 'Configuration Set');
                    $this->throwValidationException(['configuration_set_name' => ['validation' => $errorMessage]]);
                }
            }

            // Validate Tenant exists
            if (!empty($connection['tenant_name'])) {
                try {
                    $tenantInfo = $ses->getTenant($connection['tenant_name']);
                    if (isset($tenantInfo['error'])) {
                        $errorMessage = $this->parseAwsErrorMessage($tenantInfo['error'], 'Tenant');
                        $this->throwValidationException(['tenant_name' => ['validation' => $errorMessage]]);
                    }
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    $errorMessage = $this->parseAwsErrorMessage($e->getMessage(), 'Tenant');
                    $this->throwValidationException(['tenant_name' => ['validation' => $errorMessage]]);
                }
            }
        }

        return true;
    }

    /**
     * Parse AWS error message to provide user-friendly feedback
     *
     * @param string $errorMessage The raw error message
     * @param string $resourceType The type of resource (e.g., 'Tenant', 'Configuration Set')
     * @return string User-friendly error message
     */
    protected function parseAwsErrorMessage($errorMessage, $resourceType)
    {
        // Check for common error types
        if (stripos($errorMessage, 'NotFoundException') !== false || stripos($errorMessage, 'not found') !== false) {
            return sprintf(__('%s not found. Please verify the name exists in your AWS SES account.', 'fluent-smtp'), $resourceType);
        }
        
        if (stripos($errorMessage, 'ValidationException') !== false || stripos($errorMessage, 'Unprocessable') !== false) {
            return sprintf(__('%s does not exist or is invalid. Please check the name and try again.', 'fluent-smtp'), $resourceType);
        }
        
        if (stripos($errorMessage, 'AccessDenied') !== false) {
            return sprintf(__('Access denied. Your AWS credentials do not have permission to access this %s.', 'fluent-smtp'), strtolower($resourceType));
        }
        
        // Return original message with context if no specific pattern matched
        return sprintf(__('%s validation failed: %s', 'fluent-smtp'), $resourceType, $errorMessage);
    }
}
