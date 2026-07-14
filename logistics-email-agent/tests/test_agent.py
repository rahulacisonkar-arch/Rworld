import os
import sys
import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

# Set Python path to find backend modules
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.database import Base, ShipmentDraft, EmailLog
from backend.agent import validate_shipment

# Create in-memory database engine for isolated tests
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

def test_validation_missing_fields(db_session):
    """Test validation errors for empty/missing required fields"""
    draft = ShipmentDraft(
        to_name=None,
        to_company=None,
        to_address1=None,
        to_city=None,
        to_state=None,
        to_zip=None,
        sales_order_number=None,
        weight_lbs=None
    )
    db_session.add(draft)
    db_session.commit()

    status, errors = validate_shipment(draft, db_session)
    assert status == "invalid"
    assert "Missing recipient name/company" in errors
    assert "Missing recipient address line 1" in errors
    assert "Missing Sales Order number reference" in errors

def test_validation_invalid_zip(db_session):
    """Test validation detects non-US ZIP formats"""
    draft = ShipmentDraft(
        to_name="Rajesh Kumar",
        to_address1="100 Peachtree St",
        to_city="Atlanta",
        to_state="GA",
        to_zip="GA-30303", # Invalid ZIP format
        sales_order_number="SO-49204",
        weight_lbs=12.5,
        length_in=12.0,
        width_in=10.0,
        height_in=8.0
    )
    db_session.add(draft)
    db_session.commit()

    status, errors = validate_shipment(draft, db_session)
    assert status == "invalid"
    assert any("Invalid US ZIP Code format" in err for err in errors)

def test_validation_valid_shipment(db_session):
    """Test clean valid inputs pass validations"""
    draft = ShipmentDraft(
        to_name="Jane Doe",
        to_address1="456 Broadway",
        to_city="New York",
        to_state="NY",
        to_zip="10013-1234",
        sales_order_number="SO-98402",
        weight_lbs=5.0,
        length_in=6.0,
        width_in=6.0,
        height_in=6.0
    )
    db_session.add(draft)
    db_session.commit()

    status, errors = validate_shipment(draft, db_session)
    assert status == "valid"
    assert len(errors) == 0

def test_validation_duplicate_orders(db_session):
    """Test validation catches duplicates of the same Sales Order in active queue"""
    draft1 = ShipmentDraft(
        to_name="John Doe",
        to_address1="123 Main St",
        to_city="Atlanta",
        to_state="GA",
        to_zip="30303",
        sales_order_number="SO-10022",
        weight_lbs=10.0,
        length_in=10.0,
        width_in=10.0,
        height_in=10.0
    )
    draft2 = ShipmentDraft(
        to_name="John Doe",
        to_address1="123 Main St",
        to_city="Atlanta",
        to_state="GA",
        to_zip="30303",
        sales_order_number="SO-10022", # Duplicate Sales Order reference
        weight_lbs=10.0,
        length_in=10.0,
        width_in=10.0,
        height_in=10.0
    )
    db_session.add(draft1)
    db_session.add(draft2)
    db_session.commit()

    status, errors = validate_shipment(draft2, db_session)
    assert status == "invalid"
    assert any("Duplicate Sales Order" in err for err in errors)
