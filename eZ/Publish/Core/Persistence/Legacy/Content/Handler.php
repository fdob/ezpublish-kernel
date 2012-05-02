<?php
/**
 * File containing the Content Handler class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Content;
use eZ\Publish\Core\Persistence\Legacy\Content\Gateway,
    eZ\Publish\Core\Persistence\Legacy\Content\Mapper,
    eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway as LocationGateway,
    eZ\Publish\Core\Persistence\Legacy\Content\Location\Handler as LocationHandler,
    eZ\Publish\SPI\Persistence\Content\Handler as BaseContentHandler,
    eZ\Publish\SPI\Persistence\Content\CreateStruct,
    eZ\Publish\SPI\Persistence\Content\UpdateStruct,
    eZ\Publish\API\Repository\Values\Content\Query\Criterion,
    eZ\Publish\SPI\Persistence\Content\MetadataUpdateStruct,
    eZ\Publish\SPI\Persistence\Content\VersionInfo,
    eZ\Publish\SPI\Persistence\Content\Relation\CreateStruct as RelationCreateStruct,
    eZ\Publish\Core\Base\Exceptions\NotFoundException as NotFound;

/**
 * The Content Handler stores Content and ContentType objects.
 */
class Handler implements BaseContentHandler
{
    /**
     * Content gateway.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Gateway
     */
    protected $contentGateway;

    /**
     * Location gateway.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway
     */
    protected $locationGateway;

    /**
     * Location handler.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Location\Handler
     */
    public $locationHandler;

    /**
     * Mapper.
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * FieldHandler
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\FieldHandler
     */
    protected $fieldHandler;

    /**
     * Creates a new content handler.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Gateway $contentGateway
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway $locationGateway
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Mapper $mapper
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\FieldHandler $fieldHandler
     */
    public function __construct(
        Gateway $contentGateway,
        LocationGateway $locationGateway,
        Mapper $mapper,
        FieldHandler $fieldHandler
    )
    {
        $this->contentGateway = $contentGateway;
        $this->locationGateway = $locationGateway;
        $this->mapper = $mapper;
        $this->fieldHandler = $fieldHandler;
    }

    /**
     * Creates a new Content entity in the storage engine.
     *
     * The values contained inside the $content will form the basis of stored
     * entity.
     *
     * Will contain always a complete list of fields.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\CreateStruct $struct Content creation struct.
     * @param int $versionNo Used by self::copy() to maintain version numbers
     *
     * @return \eZ\Publish\SPI\Persistence\Content Content value object
     */
    public function create( CreateStruct $struct, $versionNo = 1 )
    {
        $content = $this->mapper->createContentFromCreateStruct(
            $struct,
            $versionNo
        );

        $content->contentInfo->id = $this->contentGateway->insertContentObject( $struct, $versionNo );

        $content->versionInfo = $this->mapper->createVersionInfoForContent(
            $content,
            $versionNo,
            $struct->fields,
            $content->contentInfo->mainLanguageCode
        );
        $content->versionInfo->creationDate = $struct->modified;
        $content->versionInfo->modificationDate = $struct->modified;
        $content->versionInfo->id = $this->contentGateway->insertVersion(
            $content->versionInfo, $struct->fields, $content->contentInfo->isAlwaysAvailable
        );
        $content->fields = $struct->fields;
        $content->versionInfo->names = $struct->name;

        $this->fieldHandler->createNewFields( $content );

        // Create node assignments
        foreach ( $struct->locations as $location )
        {
            $location->contentId = $content->contentInfo->id;
            $location->contentVersion = $content->versionInfo->versionNo;
            $this->locationGateway->createNodeAssignment(
                $location,
                $location->parentId,
                LocationGateway::NODE_ASSIGNMENT_OP_CODE_CREATE
            );
        }

        // Create name
        foreach ( $content->versionInfo->names as $language => $name )
        {
            $this->contentGateway->setName(
                $content->contentInfo->id,
                $content->versionInfo->versionNo,
                $name, $language
            );
        }

        return $content;
    }

    /**
     * Performs the publishing operations required to set the version identified by $updateStruct->versionNo and
     * $updateStruct->id as the published one.
     *
     * The publish procedure will:
     * - Create location nodes based on the node assignments
     * - Update the content object using the provided metadata update struct
     * - Update the node assignments
     * - Set content and version status to published
     *
     * @param int $contentId
     * @param int $versionNo
     * @param \eZ\Publish\SPI\Persistence\Content\MetadataUpdateStruct $metaDataUpdateStruct
     *
     * @return \eZ\Publish\SPI\Persistence\Content The published Content
     */
    public function publish( $contentId, $versionNo, MetaDataUpdateStruct $metaDataUpdateStruct )
    {
        $this->contentGateway->updateContent( $contentId, $metaDataUpdateStruct );
        $this->locationGateway->createLocationsFromNodeAssignments(
            $contentId,
            $versionNo
        );
        $this->setStatus( $contentId, VersionInfo::STATUS_PUBLISHED, $versionNo );

        return $this->load( $contentId, $versionNo );
    }

    /**
     * Creates a new draft version from $contentId in $version.
     *
     * Copies all fields from $contentId in $srcVersion and creates a new
     * version of the referred Content from it.
     *
     * Note: When creating a new draft in the old admin interface there will
     * also be an entry in the `eznode_assignment` created for the draft. This
     * is ignored in this implementation.
     *
     * @param int $contentId
     * @param int $srcVersion
     * @return \eZ\Publish\SPI\Persistence\Content
     */
    public function createDraftFromVersion( $contentId, $srcVersion )
    {
        $content = $this->load( $contentId, $srcVersion );

        $fields = $content->fields;

        // Create new version
        $content->versionInfo = $this->mapper->createVersionInfoForContent(
            $content,
            $this->contentGateway->getLastVersionNumber( $contentId ) + 1,
            $fields,
            $content->versionInfo->initialLanguageCode
        );
        $content->versionInfo->id = $this->contentGateway->insertVersion(
            $content->versionInfo,
            $content->fields,
            $content->contentInfo->isAlwaysAvailable
        );

        // Clone fields from previous version and append them to the new one
        $content->fields = array();
        foreach ( $fields as $field )
        {
            $newField = clone $field;
            $newField->versionNo = $content->versionInfo->versionNo;
            $content->fields[] = $newField;
        }
        $this->fieldHandler->createNewFields( $content );

        // Create name
        foreach ( $content->versionInfo->names as $language => $name )
        {
            $this->contentGateway->setName(
                $contentId,
                $content->versionInfo->versionNo,
                $name,
                $language
            );
        }

        return $content;
    }

    /**
     * Returns the raw data of a content object identified by $id, in a struct.
     *
     * A version to load must be specified. If you want to load the current
     * version of a content object use SearchHandler::findSingle() with the
     * ContentId criterion.
     *
     * Optionally a translation filter may be specified. If specified only the
     * translations with the listed language codes will be retrieved. If not,
     * all translations will be retrieved.
     *
     * @param int|string $id
     * @param int|string $version
     * @param string[] $translations
     * @return \eZ\Publish\SPI\Persistence\Content Content value object
     */
    public function load( $id, $version, $translations = null )
    {
        $rows = $this->contentGateway->load( $id, $version, $translations );

        if ( empty( $rows ) )
            throw new NotFound( 'content', "contentId: $id, versionNo: $version" );

        $contentObjects = $this->mapper->extractContentFromRows( $rows );
        $content = $contentObjects[0];

        $this->fieldHandler->loadExternalFieldData( $content );

        return $content;
    }

    /**
     * Returns the metadata object for a content identified by $contentId.
     *
     * @param int|string $contentId
     * @return \eZ\Publish\SPI\Persistence\Content\ContentInfo
     */
    public function loadContentInfo( $contentId )
    {
        return $this->mapper->extractContentInfoFromRow(
            $this->contentGateway->loadContentInfo( $contentId )
        );
    }

    /**
     * Returns the version object for a content/version identified by $contentId and $versionNo
     *
     * @param int|string $contentId
     * @param int $versionNo Version number to load
     * @return \eZ\Publish\SPI\Persistence\Content\VersionInfo
     */
    public function loadVersionInfo( $contentId, $versionNo )
    {
        return $this->mapper->extractVersionInfoFromRow(
            $this->contentGateway->loadVersionInfo( $contentId, $versionNo )
        );
    }

    /**
     * Returns all versions with draft status created by the given $userId
     *
     * @param int $userId
     *
     * @return \eZ\Publish\SPI\Persistence\Content\VersionInfo[]
     */
    public function loadDraftsForUser( $userId )
    {
        return $this->mapper->extractVersionInfoListFromRows(
            $this->contentGateway->listVersionsForUser( $userId, VersionInfo::STATUS_DRAFT )
        );
    }

    /**
     * Sets the status of object identified by $contentId and $version to $status.
     *
     * The $status can be one of VersionInfo::STATUS_DRAFT, VersionInfo::STATUS_PUBLISHED, VersionInfo::STATUS_ARCHIVED
     * When status is set to VersionInfo::STATUS_PUBLISHED content status is updated to ContentInfo::STATUS_PUBLISHED
     *
     * @param int $contentId
     * @param int $status
     * @param int $version
     * @return boolean
     */
    public function setStatus( $contentId, $status, $version )
    {
        return $this->contentGateway->setStatus( $contentId, $version, $status );
    }

    /**
     * Updates a content object meta data, identified by $contentId
     *
     * @param int $contentId
     * @param \eZ\Publish\SPI\Persistence\Content\MetadataUpdateStruct $content
     * @return \eZ\Publish\SPI\Persistence\Content\ContentInfo
     */
    public function updateMetadata( $contentId, MetadataUpdateStruct $content )
    {
        $this->contentGateway->updateContent( $contentId, $content );
        return $this->loadContentInfo( $contentId );
    }

    /**
     * Updates a content version, identified by $contentId and $versionNo
     *
     * @param int $contentId
     * @param int $versionNo
     * @param \eZ\Publish\SPI\Persistence\Content\UpdateStruct $content
     * @return \eZ\Publish\SPI\Persistence\Content
     */
    public function updateContent( $contentId, $versionNo, UpdateStruct $content )
    {
        $this->contentGateway->updateVersion( $contentId, $versionNo, $content );
        $this->fieldHandler->updateFields( $contentId, $versionNo, $content );
        foreach ( $content->name as $language => $name )
        {
            $this->contentGateway->setName(
                $contentId,
                $versionNo,
                $name, $language
            );
        }

        return $this->load( $contentId, $versionNo );
    }

    /**
     * Deletes all versions and fields, all locations (subtree), and all relations.
     *
     * Removes the relations, but not the related objects. All subtrees of the
     * assigned nodes of this content objects are removed (recursively).
     *
     * @param int $contentId
     * @return boolean
     */
    public function deleteContent( $contentId )
    {
        foreach ( $this->contentGateway->getAllLocationIds( $contentId ) as $locationId )
        {
            $this->locationHandler->removeSubtree( $locationId );
        }
    }

    /**
     * Deletes raw content data
     *
     * @param int $contentId
     */
    public function removeRawContent( $contentId )
    {
        $this->fieldHandler->deleteFields( $contentId );
        $this->contentGateway->deleteRelations( $contentId );
        $this->contentGateway->deleteVersions( $contentId );
        $this->contentGateway->deleteNames( $contentId );
        $this->contentGateway->deleteContent( $contentId );
    }

    /**
     * Deletes given version, its fields, node assignment, relations and names.
     *
     * Removes the relations, but not the related objects.
     *
     * @param int $contentId
     * @param int $versionNo
     *
     * @return boolean
     */
    public function deleteVersion( $contentId, $versionNo )
    {
        $this->locationGateway->deleteNodeAssignment( $contentId, $versionNo );
        $this->fieldHandler->deleteFields( $contentId, $versionNo );
        $this->contentGateway->deleteRelations( $contentId, $versionNo );
        $this->contentGateway->deleteVersions( $contentId, $versionNo );
        $this->contentGateway->deleteNames( $contentId, $versionNo );
    }

    /**
     * Return the versions for $contentId
     *
     * @param int $contentId
     * @return \eZ\Publish\SPI\Persistence\Content\VersionInfo[]
     */
    public function listVersions( $contentId )
    {
        return $this->mapper->extractVersionInfoListFromRows(
            $this->contentGateway->listVersions( $contentId )
        );
    }

    /**
     * Copy Content with Fields and Versions from $contentId in $version.
     *
     * Copies all fields from $contentId in $version (or all versions if false)
     * to a new object which is returned. Version numbers are maintained.
     *
     * @todo Should relations be copied? Which ones?
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException If content or version is not found
     *
     * @param mixed $contentId
     * @param mixed|null $versionNo Copy all versions if left null
     *
     * @return \eZ\Publish\SPI\Persistence\Content
     */
    public function copy( $contentId, $versionNo = null )
    {
        $currentVersionNo = isset( $versionNo ) ?
            $versionNo :
            $this->loadContentInfo( $contentId )->currentVersionNo;

        // Copy content in given version or current version
        $createStruct = $this->mapper->createCreateStructFromContent(
            $this->load( $contentId, $currentVersionNo )
        );
        $content = $this->create(
            $createStruct,
            $currentVersionNo
        );

        // If version was not passed also copy other versions
        if ( !isset( $versionNo ) )
        {
            foreach ( $this->listVersions( $contentId ) as $versionInfo )
            {
                if ( $versionInfo->versionNo === $currentVersionNo ) continue;

                $versionContent = $this->load( $contentId, $versionInfo->versionNo );

                $versionContent->contentInfo->id = $content->contentInfo->id;
                $versionContent->versionInfo->contentId = $content->contentInfo->id;
                $versionContent->versionInfo->modificationDate = $createStruct->modified;
                $versionContent->versionInfo->creationDate = $createStruct->modified;
                $versionContent->versionInfo->id = $this->contentGateway->insertVersion(
                    $versionContent->versionInfo,
                    $versionContent->fields,
                    $versionContent->contentInfo->isAlwaysAvailable
                );

                $this->fieldHandler->createNewFields( $versionContent );

                // Create name
                foreach ( $versionContent->versionInfo->names as $language => $name )
                {
                    $this->contentGateway->setName(
                        $content->contentInfo->id,
                        $versionInfo->versionNo,
                        $name,
                        $language
                    );
                }
            }
        }

        return $content;
    }

    /**
     * Creates a relation between $sourceContentId in $sourceContentVersionNo
     * and $destinationContentId with a specific $type.
     *
     * @todo Should the existence verifications happen here or is this supposed to be handled at a higher level?
     *
     * @param  \eZ\Publish\SPI\Persistence\Content\Relation\CreateStruct $relation
     * @return \eZ\Publish\SPI\Persistence\Content\Relation
     */
    public function addRelation( RelationCreateStruct $relation )
    {
        throw new \Exception( "@TODO: Not implemented yet." );
    }

    /**
     * Removes a relation by relation Id.
     *
     * @todo Should the existence verifications happen here or is this supposed to be handled at a higher level?
     *
     * @param mixed $relationId
     */
    public function removeRelation( $relationId )
    {
        throw new \Exception( "@TODO: Not implemented yet." );
    }

    /**
     * Loads relations from $sourceContentId. Optionally, loads only those with $type and $sourceContentVersionNo.
     *
     * @param mixed $sourceContentId Source Content ID
     * @param mixed|null $sourceContentVersionNo Source Content Version, null if not specified
     * @param int|null $type {@see \eZ\Publish\API\Repository\Values\Content\Relation::COMMON,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::EMBED,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::LINK,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::FIELD}
     * @return \eZ\Publish\SPI\Persistence\Content\Relation[]
     */
    public function loadRelations( $sourceContentId, $sourceContentVersionNo = null, $type = null )
    {
        throw new \Exception( "@TODO: Not implemented yet." );
    }

    /**
     * Loads relations from $contentId. Optionally, loads only those with $type.
     *
     * Only loads relations against published versions.
     *
     * @param mixed $destinationContentId Destination Content ID
     * @param int|null $type {@see \eZ\Publish\API\Repository\Values\Content\Relation::COMMON,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::EMBED,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::LINK,
     *                 \eZ\Publish\API\Repository\Values\Content\Relation::FIELD}
     * @return \eZ\Publish\SPI\Persistence\Content\Relation[]
     */
    public function loadReverseRelations( $destinationContentId, $type = null )
    {
        throw new \Exception( "@TODO: Not implemented yet." );
    }
}
