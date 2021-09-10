<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Helper\Query\Logger;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\Visitor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\GraphQl\Schema;
use Magento\GraphQl\Model\Query\Logger\LoggerInterface;

/**
 * Helper class to collect data for logging GraphQl requests
 */
class LogData
{
    /**
     * Extracts relevant information about the request
     *
     * @param RequestInterface $request
     * @param array $data
     * @param Schema|null $schema
     * @param HttpResponse|null $response
     * @return array
     */
    public function getRequestInformation(
        RequestInterface $request,
        array $data,
        Schema $schema = null,
        HttpResponse $response = null
    ) : array {
        $requestInformation = [];
        $requestInformation[LoggerInterface::HTTP_METHOD] = $request->getMethod();
        $requestInformation[LoggerInterface::STORE_HEADER] = $request->getHeader('Store') ?: '';
        $requestInformation[LoggerInterface::CURRENCY_HEADER] = $request->getHeader('Currency') ?: '';
        $requestInformation[LoggerInterface::HAS_AUTH_HEADER] = $request->getHeader('Authorization') ? 'true' : 'false';
        $requestInformation[LoggerInterface::IS_CACHEABLE] =
            ($response->getHeader('X-Magento-Tags') && $response->getHeader('X-Magento-Tags') !== '')
                ? 'true'
                : 'false';
        $requestInformation[LoggerInterface::REQUEST_LENGTH] = $request->getHeader('Content-Length') ?: '';

        if ($schema) {
            $schemaConfig = $schema->getConfig();
            $mutationOperations = $schemaConfig->getMutation()->getFields();
            $queryOperations = $schemaConfig->getQuery()->getFields();
            $requestInformation[LoggerInterface::HAS_MUTATION] = count($mutationOperations) > 0 ? 'true' : 'false';
            $requestInformation[LoggerInterface::NUMBER_OF_OPERATIONS] =
                count($mutationOperations) + count($queryOperations);
            $operationNames = array_merge(array_keys($mutationOperations), array_keys($queryOperations));
            $requestInformation[LoggerInterface::OPERATION_NAMES] =
                count($operationNames) > 0 ? implode(",", $operationNames) : 'operationNameNotFound';
        }

        $requestInformation[LoggerInterface::COMPLEXITY] = $this->getFieldCount($data['query'] ?? '');

        if ($response) {
            $requestInformation[LoggerInterface::HTTP_RESPONSE_CODE] = $response->getHttpResponseCode();
        }

        return $requestInformation;
    }

    /**
     * Gets the field count for the whole request
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param string $query
     * @return int
     */
    private function getFieldCount(string $query): int
    {
        try {
            if (!empty($query)) {
                $totalFieldCount = 0;
                $queryAst = Parser::parse(new Source($query ?: '', 'GraphQL'));
                Visitor::visit(
                    $queryAst,
                    [
                        'leave' => [
                            NodeKind::FIELD => function (Node $node) use (&$totalFieldCount) {
                                $totalFieldCount++;
                            }
                        ]
                    ]
                );
                return $totalFieldCount;
            }
        } catch (SyntaxError $syntaxError) {
        } catch (\Exception $exception) {
        }
        return 0;
    }
}
