import os
import sys
import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.database import Base, EmailLog, ShipmentDraft
from backend.email_monitor import scan_mock_inbox

# Create in-memory DB
engine = create_engine("sqlite:///:memory:")
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

@pytest.fixture(scope="function")
def db_session():
    Base.metadata.create_all(bind=engine)
    session = TestingSessionLocal()
    try:
        yield session
    finally:
        session.close()
        Base.metadata.drop_all(bind=engine)

def test_scan_mock_inbox_no_files(db_session, tmp_path, monkeypatch):
    """Test that scanning empty mock directory does not raise errors or insert records"""
    # Override settings using monkeypatch
    monkeypatch.setattr("backend.config.config.MOCK_INBOX_DIR", str(tmp_path))
    
    # Run scan
    async def run_test():
        await scan_mock_inbox(db_session)
    import asyncio
    asyncio.run(run_test())

    # Assert no emails loaded
    emails = db_session.query(EmailLog).all()
    assert len(emails) == 0

def test_scan_mock_inbox_with_file(db_session, tmp_path, monkeypatch):
    """Test scanning mock directory successfully registers incoming mail files"""
    # Setup test file
    mock_email_content = """
    From: store_atl@arteefabrics.com
    Subject: Outbound Retail Transfer SO-90302
    Body: Need 2 boxes sent to Rajesh Kumar, 100 Peachtree St, Atlanta, GA 30303.
    Weight: 15.4 lbs
    Dimensions: 12x10x8
    """
    email_file = tmp_path / "atl_transfer.txt"
    email_file.write_text(mock_email_content, encoding="utf-8")

    # Create processed subfolder so email_monitor can archive it
    os.makedirs(tmp_path / "processed", exist_ok=True)

    monkeypatch.setattr("backend.config.config.MOCK_INBOX_DIR", str(tmp_path))

    # Mock agent processing trigger to keep it offline during tests
    async def dummy_agent_process(email_id, db):
        pass
    monkeypatch.setattr("backend.email_monitor.process_email_agent", dummy_agent_process)

    async def run_test():
        await scan_mock_inbox(db_session)
    import asyncio
    asyncio.run(run_test())

    # Verify log record created
    emails = db_session.query(EmailLog).all()
    assert len(emails) == 1
    assert "atl@arteefabrics.com" in emails[0].sender
    assert "SO-90302" in emails[0].subject

    # Check that file was moved/archived to processed subdirectory
    assert not os.path.exists(email_file)
    assert os.path.exists(tmp_path / "processed" / "atl_transfer.txt")
