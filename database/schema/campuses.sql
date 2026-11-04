-- Create campuses table if it doesn't exist
CREATE TABLE IF NOT EXISTS campuses (
    Campus_ID int(11) NOT NULL AUTO_INCREMENT,
    School_Name varchar(255) NOT NULL,
    Abbreviation varchar(20) NOT NULL,
    Campus varchar(100) NOT NULL,
    Address text NOT NULL,
    Alumni_Head_Email varchar(255) DEFAULT NULL,
    Created_At timestamp NOT NULL DEFAULT current_timestamp(),
    Updated_At timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (Campus_ID),
    UNIQUE KEY Alumni_Head_Email (Alumni_Head_Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
