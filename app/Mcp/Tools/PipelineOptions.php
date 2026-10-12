<?php

namespace App\Mcp\Tools;

class PipelineOptions extends PipelineTool
{
    protected string $action = 'options';

    protected string $name = 'pipeline_options';

    protected string $description = 'Leggi stati, situazioni di pagamento e regole della pipeline. Non ci sono esiti separati. Usa i value esatti negli altri strumenti.';
}
