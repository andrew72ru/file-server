<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 09:46.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Chunk-uploaded bundle configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('chunk_upload');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->arrayNode('route_params')
                ->addDefaultsIfNotSet()
                    ->append($this->chunkSizeNode())
                    ->append($this->currentChunkSize())
                    ->append($this->chunkNumber())
                    ->append($this->totalSize())
                    ->append($this->fileField())
                    ->append($this->uniqueIdField())
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @return ScalarNodeDefinition
     */
    public function uniqueIdField(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('unique_id_field');
        $node->defaultValue('_uniqueId')->end();

        return $node;
    }

    /**
     * @return ScalarNodeDefinition
     */
    public function fileField(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('file_field');
        $node->defaultValue('file')->end();

        return $node;
    }

    /**
     * @return ScalarNodeDefinition
     */
    private function totalSize(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('total_size');
        $node->defaultValue('_totalSize')->end();

        return $node;
    }

    /**
     * @return ScalarNodeDefinition
     */
    private function chunkNumber(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('chunk_number');
        $node->defaultValue('_chunkNumber')->end();

        return $node;
    }

    /**
     * @return ScalarNodeDefinition
     */
    private function currentChunkSize(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('current_chunk_size');
        $node->defaultValue('_currentChunkSize')->end();

        return $node;
    }

    /**
     * @return ScalarNodeDefinition
     */
    private function chunkSizeNode(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('chunk_size');
        $node->defaultValue('_chunkSize')->end();

        return $node;
    }
}
