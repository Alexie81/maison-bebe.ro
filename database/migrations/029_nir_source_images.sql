ALTER TABLE nir_artifacts
    MODIFY artifact_type ENUM('pdf','xlsx','source_pdf','source_xlsx','source_xml','source_image','delivery_note') NOT NULL,
    ADD COLUMN original_filename VARCHAR(255) NULL AFTER artifact_type;
