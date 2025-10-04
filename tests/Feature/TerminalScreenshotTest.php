<?php

describe('Terminal Output Screenshot', function () {
    it('captures the default output of prayers-cli', function () {
        $output = shell_exec('php prayers-cli prayers:times');
        file_put_contents(__DIR__ . '/../../terminal_output.txt', $output);
        expect($output)->toContain('Fajr'); // Basic check for expected output
    });
});
