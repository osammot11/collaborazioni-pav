<?php

namespace App\Mcp\Tools;

class PipelineOptions extends PipelineTool
{
    protected string $action = 'options';

    protected string $name = 'pipeline_options';

    protected string $description = 'Leggi fasi, esiti, situazioni di pagamento e regole della pipeline. Usa i value esatti negli altri strumenti.';
}
