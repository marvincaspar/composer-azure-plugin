<?php

namespace MarvinCaspar\Composer;

class AzureRepository
{
    protected string $organization;
    protected string $project;
    protected string $scope;
    protected string $feed;
    protected bool $symlink;
    protected array $artifacts = [];

    public function __construct(string $organization, string $project, string $feed, bool $symlink)
    {
        $this->organization = $organization;
        $this->project = $project;
        $this->feed = $feed;
        $this->symlink = $symlink;
        $this->scope = "project";
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function getProject(): string
    {
        return $this->project;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getFeed(): string
    {
        return $this->feed;
    }

    public function getSymlink(): bool
    {
        return $this->symlink;
    }

    public function addArtifact(string $name, string $version): void
    {
        $this->artifacts[] = [
            'name' => $name,
            'version' => $version
        ];
    }

    public function updateArtifactVersion(int $index, string $version): void
    {
        $this->artifacts[$index]['version'] =  $version;
    }

    public function getArtifacts(): array
    {
        return $this->artifacts;
    }

    public function countArtifacts(): int
    {
        return count($this->artifacts);
    }
}